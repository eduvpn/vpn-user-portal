<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use Vpn\Portal\Http\Auth\AbstractAuthModule;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\UserInfo;

class DummyAuthModule extends AbstractAuthModule
{
    public function userInfo(Request $request): ?UserInfo
    {
        return new UserInfo('dummy', []);
    }
}
