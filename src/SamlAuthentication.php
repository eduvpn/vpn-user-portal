<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use fkooman\SAML\SP\Exception\SamlException;
use fkooman\SAML\SP\PrivateKey;
use fkooman\SAML\SP\PublicKey;
use fkooman\SAML\SP\SP;
use fkooman\SAML\SP\SpInfo;
use fkooman\SAML\SP\XmlIdpInfoSource;
use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\UserInfo;

class SamlAuthentication implements BeforeHookInterface, ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \fkooman\SAML\SP\SP */
    private $samlSp;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(Config $config, SeSamlSession $session)
    {
        $this->config = $config;

        /** @var string $rootUri */
        $rootUri = $config->getItem('_rootUri');
        /** @var string|null $spEntityId */
        if (null === $spEntityId = $config->optionalItem('spEntityId')) {
            $spEntityId = $rootUri.'_saml/metadata';
        }
        if (null === $requireEncryption = $config->optionalItem('requireEncryption')) {
            $requireEncryption = false;
        }
        $spInfo = new SpInfo(
            $spEntityId,
            PrivateKey::fromFile(sprintf('%s/config/sp.key', $config->getItem('_baseDir'))),
            PublicKey::fromFile(sprintf('%s/config/sp.crt', $config->getItem('_baseDir'))),
            $rootUri.'_saml/acs',
            $requireEncryption
        );
        $spInfo->setSloUrl($rootUri.'_saml/slo');

        $idpMetadata = $config->getItem('idpMetadata');
        $this->samlSp = new SP(
            $spInfo,
            new XmlIdpInfoSource([$idpMetadata]),
            $session
        );
        $this->dateTime = new DateTime();
    }

    /**
     * @return false|\LC\Common\Http\RedirectResponse|\LC\Common\Http\UserInfo
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

        if (!$this->samlSp->hasAssertion()) {
            // user not (yet) authenticated, redirect to "login" endpoint
            if (null === $authnContext = $this->config->optionalItem('authnContext')) {
                $authnContext = [];
            }

            return self::getLoginRedirect($request, $authnContext);
        }

        $samlAssertion = $this->samlSp->getAssertion();
        $samlAttributes = $samlAssertion->getAttributes();

        /** @var string $userIdAttribute */
        $userIdAttribute = $this->config->getItem('userIdAttribute');

        if (!\array_key_exists($userIdAttribute, $samlAttributes)) {
            throw new HttpException(sprintf('missing SAML user_id attribute "%s"', $userIdAttribute), 500);
        }

        $userId = $samlAttributes[$userIdAttribute][0];
        $sessionExpiresAt = null;

        $userAuthnContext = $samlAssertion->getAuthnContext();
        if (0 !== \count($this->getPermissionAttributeList())) {
            $userPermissions = $this->getPermissionList($samlAttributes);

            /** @var array<string,string>|null $permissionAuthnContext */
            if (null === $permissionAuthnContext = $this->config->optionalItem('permissionAuthnContext')) {
                $permissionAuthnContext = [];
            }

            // if we got a permission that's part of the
            // permissionAuthnContext we have to make sure we have one of
            // the listed AuthnContexts
            foreach ($permissionAuthnContext as $permission => $authnContext) {
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

            /* @var array<string,string>|null */
            if (null === $permissionSessionExpiry = $this->config->optionalItem('permissionSessionExpiry')) {
                $permissionSessionExpiry = [];
            }

            foreach ($permissionSessionExpiry as $permission => $sessionExpiry) {
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

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/_saml/login',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $request->requireQueryParameter('ReturnTo');
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

                    // if an entityId is specified, use it
                    if (null !== $idpEntityId) {
                        return new RedirectResponse($this->samlSp->login($idpEntityId, $returnTo, $authnContextList));
                    }

                    // we didn't get an IdP entityId so we MUST perform discovery
                    /** @var string $discoUrl */
                    $discoUrl = $this->config->getItem('discoUrl');

                    // perform discovery
                    $discoQuery = http_build_query(
                        [
                            'entityID' => $this->samlSp->getSpInfo()->getEntityId(),
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
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->post(
            '/_saml/acs',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $this->samlSp->handleResponse(
                        $request->requirePostParameter('SAMLResponse'),
                        $request->requirePostParameter('RelayState')
                    );

                    return new RedirectResponse($returnTo);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $this->samlSp->logout($request->requireQueryParameter('ReturnTo'));

                    return new RedirectResponse($returnTo);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/slo',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                try {
                    $returnTo = $this->samlSp->handleLogoutResponse(
                        $request->getQueryString()
                    );

                    return new RedirectResponse($returnTo);
                } catch (SamlException $e) {
                    throw new HttpException($e->getMessage(), 500, [], $e);
                }
            }
        );

        $service->get(
            '/_saml/logout',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $logoutUrl = $this->samlSp->logout($request->requireQueryParameter('ReturnTo'));

                return new RedirectResponse($logoutUrl);
            }
        );

        $service->get(
            '/_saml/metadata',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $response = new Response(200, 'application/samlmetadata+xml');
                $response->setBody($this->samlSp->metadata());

                return $response;
            }
        );
    }

    /**
     * @param array<string,array<string>> $samlAttributes
     *
     * @return array<string>
     */
    private function getPermissionList(array $samlAttributes)
    {
        $permissionList = [];
        foreach ($this->getPermissionAttributeList() as $permissionAttribute) {
            if (\array_key_exists($permissionAttribute, $samlAttributes)) {
                $permissionList = array_merge($permissionList, $samlAttributes[$permissionAttribute]);
            }
        }

        return $permissionList;
    }

    /**
     * @return array<string>
     */
    private function getPermissionAttributeList()
    {
        /** @var array<string>|string|null */
        $permissionAttributeList = $this->config->optionalItem('permissionAttribute');
        if (\is_string($permissionAttributeList)) {
            $permissionAttributeList = [$permissionAttributeList];
        }
        if (null === $permissionAttributeList) {
            $permissionAttributeList = [];
        }

        return $permissionAttributeList;
    }

    /**
     * @param array<string> $authnContext
     *
     * @return \LC\Common\Http\RedirectResponse
     */
    private function getLoginRedirect(Request $request, array $authnContext)
    {
        $httpQuery = [
            'ReturnTo' => $request->getUri(),
        ];

        if (null !== $idpEntityId = $this->config->optionalItem('idpEntityId')) {
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
}
