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
use LC\Portal\Json;
use LC\Portal\TplInterface;
use LC\Portal\Validator;

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

    public function init(ServiceInterface $service): void
    {
        $service->postBeforeAuth(
            '/_form/auth/verify',
            function (Request $request): Response {
                $this->session->remove('_form_auth_user');

                $authUser = $request->requirePostParameter('userName', fn (string $s) => Validator::re($s, Validator::REGEXP_USER_ID));
                $authPass = $request->requirePostParameter('userPass', fn (string $s) => Validator::re($s, Validator::REGEXP_USER_AUTH_PASS));
                $redirectTo = $request->requirePostParameter('_form_auth_redirect_to', fn (string $s) => self::validateRedirectTo($request, $s));

                self::validateRedirectTo($request, $redirectTo);

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
                $this->session->set('_form_auth_permission_list', Json::encode($userInfo->permissionList()));

                return new RedirectResponse($redirectTo, 302);
            }
        );
    }

    private static function validateRedirectTo(Request $request, string $redirectTo): bool
    {
        // XXX improve this, take idea from php-saml-sp
        if (false === filter_var($redirectTo, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            return false;
        }
        // extract the "host" part of the URL
        $redirectToHost = parse_url($redirectTo, PHP_URL_HOST);
        if (!\is_string($redirectToHost)) {
            return false;
        }
        if ($request->getServerName() !== $redirectToHost) {
            return false;
        }

        return true;
    }
}
