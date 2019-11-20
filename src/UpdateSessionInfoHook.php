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
use fkooman\SeCookie\SessionInterface;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\HttpClient\ServerClient;
use LC\Common\Json;

/**
 * This hook is used to update the session info in vpn-server-api.
 */
class UpdateSessionInfoHook implements BeforeHookInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateTime */
    private $dateTime;

    /** @var \DateInterval */
    private $sessionExpiry;

    public function __construct(SessionInterface $session, ServerClient $serverClient, DateInterval $sessionExpiry)
    {
        $this->session = $session;
        $this->serverClient = $serverClient;
        $this->dateTime = new DateTime();
        $this->sessionExpiry = $sessionExpiry;
    }

    /**
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

        /** @var \LC\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];

        // check if the authentication backend wants to override the sessionExpiry
        if (null === $sessionExpiresAt = $userInfo->getSessionExpiresAt()) {
            $sessionExpiresAt = date_add(clone $this->dateTime, $this->sessionExpiry);
        }

        $this->serverClient->post(
            'user_update_session_info',
            [
                'user_id' => $userInfo->getUserId(),
                'session_expires_at' => $sessionExpiresAt->format(DateTime::ATOM),
                'permission_list' => Json::encode($userInfo->getPermissionList()),
            ]
        );
        $this->session->set('_update_session_info', true);
    }
}
