<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SeCookie\Cookie;
use LC\Portal\Http\CookieInterface;

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

    public function get(string $cookieName): ?string
    {
        if (!\array_key_exists($cookieName, $_COOKIE)) {
            return null;
        }

        if (!\is_string($_COOKIE[$cookieName])) {
            return null;
        }

        return $_COOKIE[$cookieName];
    }
}
