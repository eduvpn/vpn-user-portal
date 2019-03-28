<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use DateInterval;
use DateTime;
use fkooman\SeCookie\SessionInterface;
use LetsConnect\Common\Http\BeforeHookInterface;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\Json;

/**
 * This hook is used to update the session info in vpn-server-api.
 */
class UpdateSessionInfoHook implements BeforeHookInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateTime */
    private $dateTime;

    /** @var \DateInterval */
    private $sessionExpiry;

    /**
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(SessionInterface $session, ServerClient $serverClient, DateInterval $sessionExpiry)
    {
        $this->session = $session;
        $this->serverClient = $serverClient;
        $this->dateTime = new DateTime();
        $this->sessionExpiry = $sessionExpiry;
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

        if ($this->session->has('_update_session_info')) {
            // only sent the ping once per browser session, not on every
            // request
            return false;
        }

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }

        /** @var \LetsConnect\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        $this->serverClient->post(
            'user_update_session_info',
            [
                'user_id' => $userInfo->getUserId(),
                'session_expires_at' => date_add(clone $this->dateTime, $this->sessionExpiry)->format(DateTime::ATOM),
                'permission_list' => Json::encode($userInfo->getPermissionList()),
            ]
        );
        $this->session->set('_update_session_info', true);
    }
}
