<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\SeCookie\Cookie;

class SeCookie implements CookieInterface
{
    private Cookie $cookie;

    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    public function set(string $cookieName, string $cookieValue): void
    {
        $this->cookie->set($cookieName, $cookieValue);
    }
}
