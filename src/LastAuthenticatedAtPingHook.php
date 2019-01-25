<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\SeCookie\SessionInterface;
use LetsConnect\Common\Http\BeforeHookInterface;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\Json;

/**
 * This hook is used to record the time stamp of the last user authentication
 * The VPN server wants to know the "last_authenticated_at" time of the user.
 */
class LastAuthenticatedAtPingHook implements BeforeHookInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /**
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(SessionInterface $session, ServerClient $serverClient)
    {
        $this->session = $session;
        $this->serverClient = $serverClient;
    }

    /**
     * @param \LetsConnect\Common\Http\Request $request
     * @param array                            $hookData
     *
     * @return false|void
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_form/auth/verify',
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

        if ($this->session->has('_last_authenticated_at_ping_sent')) {
            // only sent the ping once per browser session, not on every
            // request
            return false;
        }
        if (!array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \LetsConnect\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        $this->serverClient->post(
            'last_authenticated_at_ping',
            [
                'user_id' => $userInfo->id(),
                'permission_list' => Json::encode($userInfo->permissionList()),
            ]
        );
        $this->session->set('_last_authenticated_at_ping_sent', true);
    }
}
