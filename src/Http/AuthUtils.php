<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class AuthUtils
{
    public static function requireAdmin(array $hookData): void
    {
        if (false === $hookData['is_admin']) {
            throw new HttpException('user is not an administrator', 403);
        }
    }
}
