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

/**
 * This hook is used to update the session info in vpn-server-api.
 */
class UpdateSessionInfoHook implements BeforeHookInterface
{
    /** @var Storage */
    private $storage;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \DateTime */
    private $dateTime;

    /** @var \DateInterval */
    private $sessionExpiry;

    /**
     * @param Storage                            $storage
     * @param \fkooman\SeCookie\SessionInterface $session
     * @param \DateInterval                      $sessionExpiry
     */
    public function __construct(Storage $storage, SessionInterface $session, DateInterval $sessionExpiry)
    {
        $this->storage = $storage;
        $this->session = $session;
        $this->sessionExpiry = $sessionExpiry;
        $this->dateTime = new DateTime();
    }

    /**
     * @param \LC\Common\Http\Request $request
     * @param array                   $hookData
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

        /** @var \LC\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];

        // check if the authentication backend wants to override the sessionExpiry
        if (null === $sessionExpiresAt = $userInfo->getSessionExpiresAt()) {
            $sessionExpiresAt = date_add(clone $this->dateTime, $this->sessionExpiry);
        }

        // XXX we can just provide the UserInfo object to the method perhaps?
        $this->storage->updateSessionInfo($userInfo->getUserId(), $sessionExpiresAt, $userInfo->getPermissionList());
        $this->session->set('_update_session_info', true);
    }
}
