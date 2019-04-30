<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class AuthUtils
{
    /**
     * @return void
     */
    public static function requireUser(array $hookData, array $userList)
    {
        /** @var UserInfo */
        $userInfo = $hookData['auth'];

        $userId = $userInfo->getUserId();
        if (!\in_array($userId, $userList, true)) {
            throw new HttpException(
                sprintf(
                    'user "%s" is not allowed to perform this operation',
                    $userId
                ),
                403
            );
        }
    }

    /**
     * @return void
     */
    public static function requireAdmin(array $hookData)
    {
        if (false === $hookData['is_admin']) {
            throw new HttpException('user is not an administrator', 403);
        }
    }
}
