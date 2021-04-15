<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

interface AuthModuleInterface
{
    public function userInfo(Request $request): ?UserInfoInterface;

    // XXX why ever return null?!
    public function startAuth(Request $request): ?Response;

    public function triggerLogout(Request $request): Response;

    public function supportsLogout(): bool;
}
