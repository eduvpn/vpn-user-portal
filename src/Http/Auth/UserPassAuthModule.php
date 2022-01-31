<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Http\HtmlResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\SessionInterface;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\Json;
use Vpn\Portal\TplInterface;

class UserPassAuthModule extends AbstractAuthModule
{
    protected TplInterface $tpl;
    private SessionInterface $session;

    public function __construct(SessionInterface $session, TplInterface $tpl)
    {
        $this->session = $session;
        $this->tpl = $tpl;
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

        return new UserInfo(
            $authUser,
            $permissionList
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
