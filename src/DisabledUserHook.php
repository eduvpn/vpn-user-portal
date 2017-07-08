<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\HttpClient\ServerClient;

/**
 * This hook is used to check if a user is disabled before allowing any other
 * actions except login.
 */
class DisabledUserHook implements BeforeHookInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    public function __construct(ServerClient $serverClient)
    {
        $this->serverClient = $serverClient;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        if ('POST' === $request->getRequestMethod() && '/_form/auth/verify' === $request->getPathInfo()) {
            return false;
        }
        if ('POST' === $request->getRequestMethod() && '/_oauth/token' === $request->getPathInfo()) {
            return false;
        }

        if (!array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        $userId = $hookData['auth'];
        if (is_null($userId)) {
            throw new HttpException('unable to determine user ID', 500);
        }

        if ($this->serverClient->get('is_disabled_user', ['user_id' => $userId])) {
            // user is disabled, show a special message
            throw new HttpException('account disabled', 403);
        }
    }
}
