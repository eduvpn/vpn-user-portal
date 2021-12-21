<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Auth\CredentialValidatorInterface;
use Vpn\Portal\Json;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;

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

                $authUser = $request->requirePostParameter('userName', fn (string $s) => Validator::userId($s));
                $authPass = $request->requirePostParameter('userPass', fn (string $s) => Validator::userAuthPass($s));
                $redirectTo = $request->requirePostParameter('_form_auth_redirect_to', fn (string $s) => Validator::matchesOrigin($request->getOrigin(), $s));

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
                $this->session->set('_form_auth_user', $userInfo->userId());
                $this->session->set('_form_auth_permission_list', Json::encode($userInfo->permissionList()));

                return new RedirectResponse($redirectTo, 302);
            }
        );
    }
}
