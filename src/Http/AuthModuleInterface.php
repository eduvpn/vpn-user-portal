<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

interface AuthModuleInterface
{
    public function init(ServiceInterface $service): void;

    public function userInfo(Request $request): ?UserInfo;

    public function startAuth(Request $request): ?Response;

    public function triggerLogout(Request $request): Response;
}
