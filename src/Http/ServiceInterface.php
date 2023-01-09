<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Closure;

interface ServiceInterface
{
    /**
     * @param Closure(Request,UserInfo):Response $closure
     */
    public function get(string $pathInfo, Closure $closure): void;

    /**
     * @param Closure(Request,UserInfo):Response $closure
     */
    public function post(string $pathInfo, Closure $closure): void;

    /**
     * @param Closure(Request):Response $closure
     */
    public function postBeforeAuth(string $pathInfo, Closure $closure): void;
}
