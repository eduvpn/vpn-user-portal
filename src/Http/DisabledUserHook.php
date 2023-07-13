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

/**
 * This hook is used to check if a user is disabled before allowing any other
 * actions except login.
 */
class DisabledUserHook extends AbstractHook implements HookInterface
{
    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        if ($userInfo->isDisabled()) {
            throw new HttpException('your account has been disabled by an administrator', 403);
        }

        return null;
    }
}
