<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

/**
 * "Hooks" can extend this class to avoid needing to "implement" the method
 * they don't use. Typically a hook runs either *before* or *after*
 * authentication.
 */
class AbstractHook implements BeforeHookInterface
{
    public function beforeAuth(Request $request): ?Response
    {
        return null;
    }

    public function afterAuth(UserInfoInterface $userInfo, Request $request): ?Response
    {
        return null;
    }
}
