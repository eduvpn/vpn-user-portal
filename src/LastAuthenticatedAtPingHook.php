<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\SeCookie\SessionInterface;
use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Json;

/**
 * This hook is used to record the time stamp of the last user authentication
 * The VPN server wants to know the "last_authenticated_at" time of the user.
 */
class LastAuthenticatedAtPingHook implements BeforeHookInterface
{
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /**
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \SURFnet\VPN\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(SessionInterface $session, ServerClient $serverClient)
    {
        $this->session = $session;
        $this->serverClient = $serverClient;
    }

    /**
     * @param \SURFnet\VPN\Common\Http\Request $request
     * @param array                            $hookData
     *
     * @return false|void
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if ($this->session->has('_last_authenticated_at_ping_sent')) {
            // only sent the ping once per browser session, not on every
            // request
            return false;
        }
        if ('POST' === $request->getRequestMethod() && '/_form/auth/verify' === $request->getPathInfo()) {
            return false;
        }

        if (!array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \SURFnet\VPN\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        $this->serverClient->post(
            'last_authenticated_at_ping',
            [
                'user_id' => $userInfo->id(),
                'entitlement_list' => Json::encode($userInfo->entitlementList()),
            ]
        );
        $this->session->set('_last_authenticated_at_ping_sent', true);
    }
}
