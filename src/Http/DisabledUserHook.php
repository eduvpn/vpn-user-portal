<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Storage;

/**
 * This hook is used to check if a user is disabled before allowing any other
 * actions except login.
 */
class DisabledUserHook extends AbstractHook implements HookInterface
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        if (null !== $dbUserInfo = $this->storage->userInfo($userInfo->userId())) {
            if ($dbUserInfo->isDisabled()) {
                throw new HttpException('your account has been disabled by an administrator', 403);
            }
        }

        return null;
    }
}
