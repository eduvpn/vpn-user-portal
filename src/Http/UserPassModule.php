<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Auth\CredentialValidatorInterface;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\TplInterface;

class UserPassModule implements ServiceModuleInterface
{
    protected TplInterface $tpl;
    private CredentialValidatorInterface $credentialValidator;
    private SessionInterface $session;

    public function __construct(
        CredentialValidatorInterface $credentialValidator,
        SessionInterface $session,
        TplInterface $tpl
    ) {
        $this->credentialValidator = $credentialValidator;
        $this->session = $session;
        $this->tpl = $tpl;
    }

    public function init(Service $service): void
    {
        $service->postBeforeAuth(
            '/_form/auth/verify',
            function (Request $request): Response {
                $this->session->remove('_form_auth_user');

                // LDAP treats user "foo" and "foo " as the same user, but the
                // VPN portal does not, creating "ghost" users, so trim the
                // userName (ISSUE vpn-user-portal#151)
                $authUser = trim($request->requirePostParameter('userName'));
                $authPass = $request->requirePostParameter('userPass');
                $redirectTo = $request->requirePostParameter('_form_auth_redirect_to');

                // validate the URL
                if (false === filter_var($redirectTo, \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED)) {
                    throw new HttpException('invalid redirect_to URL', 400);
                }
                // extract the "host" part of the URL
                $redirectToHost = parse_url($redirectTo, \PHP_URL_HOST);
                if (!\is_string($redirectToHost)) {
                    throw new HttpException('invalid redirect_to URL, unable to extract host', 400);
                }
                if ($request->getServerName() !== $redirectToHost) {
                    throw new HttpException('redirect_to does not match expected host', 400);
                }

                if (false === $userInfo = $this->credentialValidator->isValid($authUser, $authPass)) {
                    // invalid authentication
                    $responseBody = $this->tpl->render(
                        'formAuthentication',
                        [
                            '_form_auth_invalid_credentials' => true,
                            '_form_auth_invalid_credentials_user' => $authUser,
                            '_form_auth_redirect_to' => $redirectTo,
                            '_show_logout_button' => false,
                        ]
                    );

                    return new HtmlResponse($responseBody);
                }

                $permissionList = $userInfo->permissionList();
                $this->session->regenerate();
                $this->session->set('_form_auth_user', $userInfo->userId());
                // XXX use something better than serialize
                $this->session->set('_form_auth_permission_list', serialize($permissionList));

                return new RedirectResponse($redirectTo, 302);
            }
        );

        $service->get(
            '/_form/auth/logout',
            function (UserInfo $userInfo, Request $request): Response {
                $this->session->destroy();

                return new RedirectResponse($request->requireQueryParameter('ReturnTo'));
            }
        );
    }
}
