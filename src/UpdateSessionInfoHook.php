<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use LC\Portal\Http\BeforeHookInterface;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\SessionInterface;

/**
 * This hook is used to update the session info.
 */
class UpdateSessionInfoHook implements BeforeHookInterface
{
    /** @var \LC\Portal\Http\SessionInterface */
    private $session;

    /** @var Storage */
    private $storage;

    /** @var \DateTime */
    private $dateTime;

    /** @var \DateInterval */
    private $sessionExpiry;

    public function __construct(SessionInterface $session, Storage $storage, DateInterval $sessionExpiry)
    {
        $this->session = $session;
        $this->storage = $storage;
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
                '/_logout',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        if ('yes' === $this->session->get('_update_session_info')) {
            // only update the session info once per browser session, not on
            // every request
            return false;
        }

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }

        /** @var \LC\Portal\Http\UserInfo */
        $userInfo = $hookData['auth'];

        // check if the authentication backend wants to override the sessionExpiry
        if (null === $sessionExpiresAt = $userInfo->getSessionExpiresAt()) {
            $sessionExpiresAt = date_add(clone $this->dateTime, $this->sessionExpiry);
        }
        // XXX if the authentication backend overrides the sessionExpiresAt, we
        // must validate it is not in the past... also, probably get rid of this!
        $this->storage->updateSessionInfo($userInfo->getUserId(), $sessionExpiresAt, $userInfo->getPermissionList());
        // XXX maybe not necessary to log this anymore...
        $this->storage->addUserMessage(
            $userInfo->getUserId(),
            'notification',
            sprintf(
                'updated session info {permission_list: [%s], expires_at: %s}',
                implode(' ', $userInfo->getPermissionList()),
                $sessionExpiresAt->format(DateTime::ATOM)
            ),
            $this->dateTime
        );
        $this->session->set('_update_session_info', 'yes');
    }
}
