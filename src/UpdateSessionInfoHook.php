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
use DateTimeImmutable;
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
    private SessionInterface $session;
    private Storage $storage;
    private DateTimeImmutable $dateTime;
    private DateInterval $sessionExpiry;

    public function __construct(SessionInterface $session, Storage $storage, DateInterval $sessionExpiry)
    {
        $this->session = $session;
        $this->storage = $storage;
        $this->dateTime = new DateTimeImmutable();
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

        $sessionExpiresAt = $this->dateTime->add($this->sessionExpiry);
        $this->storage->updateSessionInfo($userInfo->getUserId(), $sessionExpiresAt, $userInfo->getPermissionList());
        $this->session->set('_update_session_info', 'yes');
    }
}
