<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

interface ServiceInterface
{
    public function get(string $pathInfo, callable $callback): void;

    public function post(string $pathInfo, callable $callback): void;

    public function postBeforeAuth(string $pathInfo, callable $callback): void;
}
