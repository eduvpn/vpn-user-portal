<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\BeforeHookInterface;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;

/**
 * This hook is used to check if a user is disabled before allowing any other
 * actions except login.
 */
class DisabledUserHook implements BeforeHookInterface
{
    /** @var Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function executeBefore(Request $request, array $hookData): void
    {
        $whiteList = [
            'POST' => [
                '/_form/auth/verify',
                '/_logout',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return;
        }

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \LC\Portal\Http\UserInfo */
        $userInfo = $hookData['auth'];

        if ($this->storage->isDisabledUser($userInfo->getUserId())) {
            throw new HttpException('account disabled', 403);
        }
    }
}
