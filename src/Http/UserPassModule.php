<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Auth\CredentialValidatorInterface;
use Vpn\Portal\Json;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;

// XXX can we merge this with Http/Auth/UserPassAuthModule?!
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
            '/_user_pass_auth/verify',
            function (Request $request): Response {
                $this->session->remove('_user_pass_auth_user_id');
                $this->session->remove('_user_pass_auth_permission_list');

                $authUser = $request->requirePostParameter('userName', fn (string $s) => Validator::userId($s));
                $authPass = $request->requirePostParameter('userPass', fn (string $s) => Validator::userAuthPass($s));
                $redirectTo = $request->requirePostParameter('_user_pass_auth_redirect_to', fn (string $s) => Validator::matchesOrigin($request->getOrigin(), $s));

                if (false === $userInfo = $this->credentialValidator->isValid($authUser, $authPass)) {
                    // invalid authentication
                    $responseBody = $this->tpl->render(
                        'userPassAuth',
                        [
                            '_user_pass_auth_invalid_credentials' => true,
                            '_user_pass_auth_invalid_credentials_user' => $authUser,
                            '_user_pass_auth_redirect_to' => $redirectTo,
                            'showLogoutButton' => false,
                        ]
                    );

                    return new HtmlResponse($responseBody);
                }
                $this->session->set('_user_pass_auth_user_id', $userInfo->userId());
                $this->session->set('_user_pass_auth_permission_list', Json::encode($userInfo->permissionList()));

                return new RedirectResponse($redirectTo);
            }
        );
    }
}
