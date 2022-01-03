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
        if (null === $authUser = $this->session->get('_form_auth_user')) {
            return null;
        }

        $permissionList = [];
        if (null !== $sessionValue = $this->session->get('_form_auth_permission_list')) {
            $permissionList = Json::decode($sessionValue);
        }

        return new UserInfo(
            $authUser,
            $permissionList
        );
    }

    public function startAuth(Request $request): ?Response
    {
        // any other URL, enforce authentication
        $responseBody = $this->tpl->render(
            'formAuthentication',
            [
                '_form_auth_invalid_credentials' => false,
                '_form_auth_redirect_to' => $request->getUri(),
                '_show_logout_button' => false,
            ]
        );

        return new HtmlResponse($responseBody, [], 200);
    }
}
