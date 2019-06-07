<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateInterval;
use DateTime;
use fkooman\SAML\SP\Exception\SamlException;
use fkooman\SAML\SP\PrivateKey;
use fkooman\SAML\SP\PublicKey;
use fkooman\SAML\SP\SP;
use fkooman\SAML\SP\SpInfo;
use fkooman\SAML\SP\XmlIdpInfoSource;
use LC\Portal\Config\SamlAuthenticationConfig;
use LC\Portal\Http\Exception\HttpException;

class SamlModule implements BeforeHookInterface, ServiceModuleInterface
{
    /** @var \LC\Portal\Config\SamlAuthenticationConfig */
    private $samlAuthenticationConfig;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(SamlAuthenticationConfig $samlAuthenticationConfig)
    {
        $this->samlAuthenticationConfig = $samlAuthenticationConfig;
        $this->dateTime = new DateTime();
    }

    /**
     * @return false|\LC\Portal\Http\RedirectResponse|\LC\Portal\Http\UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_saml/acs',
                '/_logout',
            ],
            'GET' => [
                '/_saml/login',
                '/_saml/logout',
                '/_saml/metadata',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        if (false === $samlAssertion = $this->getSamlSp($request)->getAssertion()) {
            // user not (yet) authenticated, redirect to "login" endpoint
            return self::getLoginRedirect($request, $this->samlAuthenticationConfig->getAuthnContext());
        }

        $samlAttributes = $samlAssertion->getAttributes();

        $userIdAttribute = $this->samlAuthenticationConfig->getUserIdAttribute();
        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }

        $userId = $samlAttributes[$userIdAttribute][0];
        $sessionExpiresAt = null;

        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (0 !== \count($this->samlAuthenticationConfig->getPermissionAttributeList())) {
            $userPermissions = $this->getPermissionList($samlAttributes);

            // if we got a permission that's part of the
            // permissionAuthnContext we have to make sure we have one of
            // the listed AuthnContexts
            foreach ($this->samlAuthenticationConfig->getPermissionAuthnContext() as $permission => $authnContext) {
                if (\in_array($permission, $userPermissions, true)) {
                    if (!\in_array($userAuthnContext, $authnContext, true)) {
                        // we do not have the required AuthnContext, trigger login
                        // and request the first acceptable AuthnContext
                        return self::getLoginRedirect($request, $authnContext);
                    }
                }
            }

            // if we got a permission that's part of the
            // permissionSessionExpiry we use it to determine the time we want
            // the session to expire
            $minSessionExpiresAt = null;
            foreach ($this->samlAuthenticationConfig->getPermissionSessionExpiry() as $permission => $sessionExpiry) {
                if (\in_array($permission, $userPermissions, true)) {
                    // make sure we take the sessionExpiresAt belonging to the
                    // permission that has the shortest expiry configured
                    $tmpSessionExpiresAt = date_add(clone $this->dateTime, new DateInterval($sessionExpiry));
                    if (null === $minSessionExpiresAt) {
                        $minSessionExpiresAt = $tmpSessionExpiresAt;
                    }
                    if ($tmpSessionExpiresAt < $minSessionExpiresAt) {
                        $minSessionExpiresAt = $tmpSessionExpiresAt;
                    }
                }
            }

            // take the minimum
            $sessionExpiresAt = $minSessionExpiresAt;
        }

        $userInfo = new UserInfo(
            $userId,
            $this->getPermissionList($samlAttributes)
        );

        if (null !== $sessionExpiresAt) {
            $userInfo->setSessionExpiresAt($sessionExpiresAt);
        }

        return $userInfo;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/_saml/login',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $relayState = $request->requireQueryParameter('ReturnTo');
                    $idpEntityId = $request->optionalQueryParameter('IdP');
                    $authnContextQuery = $request->optionalQueryParameter('AuthnContext');
                    $authnContextList = null !== $authnContextQuery ? explode(',', $authnContextQuery) : [];

                    // verify the requested AuthnContext in the whitelist
                    $acceptableAuthnContextList = [
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:Password',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:X509',
                        'urn:oasis:names:tc:SAML:2.0:ac:classes:TimesyncToken',
                        // https://wiki.surfnet.nl/display/SsID/Using+Levels+of+Assurance+to+express+strength+of+authentication
                        'http://test.surfconext.nl/assurance/loa1',
                        'http://pilot.surfconext.nl/assurance/loa1',
                        'http://surfconext.nl/assurance/loa1',
                        'http://test.surfconext.nl/assurance/loa2',
                        'http://pilot.surfconext.nl/assurance/loa2',
                        'http://surfconext.nl/assurance/loa2',
                        'http://test.surfconext.nl/assurance/loa3',
                        'http://pilot.surfconext.nl/assurance/loa3',
                        'http://surfconext.nl/assurance/loa3',
                    ];
                    foreach ($authnContextList as $authnContext) {
                        if (!\in_array($authnContext, $acceptableAuthnContextList, true)) {
                            throw new HttpException(sprintf('unsupported requested AuthnContext "%s"', $authnContext), 400);
                        }
                    }

                    $samlSp = $this->getSamlSp($request);

                    // if an entityId is specified, use it
                    if (null !== $idpEntityId) {
                        return new RedirectResponse($samlSp->login($idpEntityId, $relayState, $authnContextList));
                    }

                    // we didn't get an IdP entityId so we MUST perform discovery
                    if (null === $discoUrl = $this->samlAuthenticationConfig->getDiscoUrl()) {
                        throw new HttpException('no IdP specified, and no discovery service configured', 500);
                    }

                    // perform discovery
                    $discoQuery = http_build_query(
                        [
                            'entityID' => $samlSp->getSpInfo()->getEntityId(),
                            'returnIDParam' => 'IdP',
                            'return' => $request->getUri(),
                        ]
                    );

                    $querySeparator = false === strpos($discoUrl, '?') ? '?' : '&';

                    return new RedirectResponse(
                        sprintf(
                            '%s%s%s',
                            $discoUrl,
                            $querySeparator,
                            $discoQuery
                        )
                    );
                } catch (SamlException $e) {
                    throw new HttpException('user authentication failed', 500, [], $e);
                }
            }
        );

        $service->post(
            '/_saml/acs',
            function (Request $request, array $hookData): Response {
                try {
                    $this->getSamlSp($request)->handleResponse(
                        $request->requirePostParameter('SAMLResponse')
                    );

                    return new RedirectResponse($request->requirePostParameter('RelayState'));
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            function (Request $request, array $hookData): Response {
                try {
                    $logoutUrl = $this->getSamlSp($request)->logout($request->requireQueryParameter('ReturnTo'));

                    return new RedirectResponse($logoutUrl);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/slo',
            function (Request $request, array $hookData): Response {
                try {
                    $this->getSamlSp($request)->handleLogoutResponse(
                        $request->getQueryString()
                    );

                    return new RedirectResponse($request->requireQueryParameter('RelayState'));
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            function (Request $request, array $hookData): Response {
                $logoutUrl = $this->getSamlSp($request)->logout($request->requireQueryParameter('ReturnTo'));

                return new RedirectResponse($logoutUrl);
            }
        );

        $service->get(
            '/_saml/metadata',
            function (Request $request, array $hookData): Response {
                $response = new Response(200, 'application/samlmetadata+xml');
                $response->setBody($this->getSamlSp($request)->metadata());

                return $response;
            }
        );
    }

    /**
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<string>
     */
    private function getPermissionList(array $samlAttributes): array
    {
        $permissionList = [];
        foreach ($this->samlAuthenticationConfig->getPermissionAttributeList() as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                foreach ($samlAttributes[$permissionAttribute] as $samlAttributeValue) {
                    $permissionList[] = sprintf('%s!%s', $permissionAttribute, $samlAttributeValue);
                }
            }
        }

        return $permissionList;
    }

    /**
     * @param array<string> $authnContext
     */
    private function getLoginRedirect(Request $request, array $authnContext): RedirectResponse
    {
        $httpQuery = [
            'ReturnTo' => $request->getUri(),
        ];
        if (null !== $idpEntityId = $this->samlAuthenticationConfig->getIdpEntityId()) {
            $httpQuery['IdP'] = $idpEntityId;
        }
        if (0 !== \count($authnContext)) {
            $httpQuery['AuthnContext'] = implode(',', $authnContext);
        }

        // redirect to SamlModule "login" endpoint
        return new RedirectResponse(
            sprintf(
                '%s_saml/login?%s',
                $request->getRootUri(),
                http_build_query($httpQuery)
            )
        );
    }

    private function getSamlSp(Request $request): SP
    {
        if (null === $spEntityId = $this->samlAuthenticationConfig->getSpEntityId()) {
            $spEntityId = $request->getRootUri().'_saml/metadata';
        }

        // XXX maybe move this in constructor?
        $configDir = \dirname(\dirname(__DIR__)).'/config';

        $spInfo = new SpInfo(
            $spEntityId,
            PrivateKey::fromFile(sprintf('%s/saml.key', $configDir)),
            PublicKey::fromFile(sprintf('%s/saml.crt', $configDir)),
            $request->getRootUri().'_saml/acs'
        );
        $spInfo->setSloUrl($request->getRootUri().'_saml/slo');
        $samlSp = new SP(
            $spInfo,
            new XmlIdpInfoSource($this->samlAuthenticationConfig->getIdpMetadata())
        );

        return $samlSp;
    }
}
