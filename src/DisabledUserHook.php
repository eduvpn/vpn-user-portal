<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\Service;

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

        if (!\array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        /** @var \LC\Common\Http\UserInfo */
        $userInfo = $hookData['auth'];
        $userId = $userInfo->getUserId();
        if ($this->storage->isDisabledUser($userId)) {
            // user is disabled, show a special message
            throw new HttpException(sprintf('account for user "%s" disabled', $userId), 403);
        }
    }
}
