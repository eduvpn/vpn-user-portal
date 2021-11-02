<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SeCookie\Cookie;
use LC\Common\Http\CookieInterface;

class SeCookie implements CookieInterface
{
    /** @var \fkooman\SeCookie\Cookie */
    private $cookie;

    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @param string $cookieName
     * @param string $cookieValue
     *
     * @return void
     */
    public function set($cookieName, $cookieValue)
    {
        $this->cookie->set($cookieName, $cookieValue);
    }

    /**
     * @param string $cookieName
     *
     * @return string|null
     */
    public function get($cookieName)
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
