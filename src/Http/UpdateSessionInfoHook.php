<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateInterval;
use DateTimeImmutable;
use LC\Portal\Storage;

/**
 * This hook is used to update the session info.
 */
class UpdateSessionInfoHook extends AbstractHook implements BeforeHookInterface
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

    public function afterAuth(UserInfoInterface $userInfo, Request $request): ?Response
    {
        if ('yes' === $this->session->get('_update_session_info')) {
            // only update the session info once per browser session, not on
            // every request
            return null;
        }

        $sessionExpiresAt = $this->dateTime->add($this->sessionExpiry);
        $this->storage->updateSessionInfo($userInfo->getUserId(), $sessionExpiresAt, $userInfo->getPermissionList());
        $this->session->set('_update_session_info', 'yes');

        return null;
    }
}
