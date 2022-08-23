<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Http\Auth\Exception\CredentialValidatorException;
use Vpn\Portal\Http\HtmlResponse;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\ServiceInterface;
use Vpn\Portal\Http\SessionInterface;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\Json;
use Vpn\Portal\LoggerInterface;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;

class UserPassAuthModule extends AbstractAuthModule
{
    protected TplInterface $tpl;
    private CredentialValidatorInterface $credentialValidator;
    private SessionInterface $session;
    private LoggerInterface $logger;

    public function __construct(CredentialValidatorInterface $credentialValidator, SessionInterface $session, TplInterface $tpl, LoggerInterface $logger)
    {
        $this->credentialValidator = $credentialValidator;
        $this->session = $session;
        $this->tpl = $tpl;
        $this->logger = $logger;
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

                try {
                    $userInfo = $this->credentialValidator->validate($authUser, $authPass);
                    $this->session->set('_user_pass_auth_user_id', $userInfo->userId());
                    $this->session->set('_user_pass_auth_permission_list', Json::encode($userInfo->permissionList()));

                    return new RedirectResponse($redirectTo);
                } catch (CredentialValidatorException $e) {
                    $this->logger->warning('Unable to validate credentials: '.$e->getMessage());

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
            }
        );
    }

    public function userInfo(Request $request): ?UserInfo
    {
        if (null === $authUser = $this->session->get('_user_pass_auth_user_id')) {
            return null;
        }

        $permissionList = [];
        if (null !== $sessionValue = $this->session->get('_user_pass_auth_permission_list')) {
            $permissionList = Json::decode($sessionValue);
        }
        $settings = [];
        $settings['permissionList'] = $permissionList;
        return new UserInfo(
            $authUser,
            $settings
        );
    }

    public function startAuth(Request $request): ?Response
    {
        $responseBody = $this->tpl->render(
            'userPassAuth',
            [
                '_user_pass_auth_invalid_credentials' => false,
                '_user_pass_auth_redirect_to' => $request->getUri(),
                'showLogoutButton' => false,
            ]
        );

        return new HtmlResponse($responseBody, [], 200);
    }
}
