<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

interface HookInterface
{
    public function beforeAuth(Request $request): ?Response;

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response;
}
