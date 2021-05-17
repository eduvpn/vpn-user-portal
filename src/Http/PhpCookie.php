<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class PhpCookie implements CookieInterface
{
    private array $cookieOptions;

    public function __construct(bool $secureCookie, string $cookiePath)
    {
        $this->cookieOptions = [
            'expires' => time() + 90 * 60 * 60 * 24,
            'path' => $cookiePath,
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public function set(string $cookieName, string $cookieValue): void
    {
        setcookie($cookieName, $cookieValue, $this->cookieOptions);
    }
}
