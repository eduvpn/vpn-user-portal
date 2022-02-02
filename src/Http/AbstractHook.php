<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

/**
 * "Hooks" can extend this class to avoid needing to "implement" the method
 * they don't use. Typically a hook runs either *before* or *after*
 * authentication.
 */
class AbstractHook implements HookInterface
{
    public function beforeAuth(Request $request): ?Response
    {
        return null;
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        return null;
    }
}
