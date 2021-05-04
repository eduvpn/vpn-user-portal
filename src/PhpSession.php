<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\SessionInterface;

/**
 * XXX implement server side session expiry, e.g. after 30 minutes.
 */
class PhpSession implements SessionInterface
{
    const SESSION_NAME = 'SID';

    public function __construct(bool $secureCookie, string $cookiePath)
    {
        $sessionOptions = [
            'cookie_samesite' => 'Strict',
            'cookie_secure' => $secureCookie,
            'cookie_httponly' => true,
            'cookie_path' => $cookiePath,
        ];

        session_name(self::SESSION_NAME);
        session_start($sessionOptions);
    }

    public function regenerate(): void
    {
        session_regenerate_id();
    }

    public function get(string $sessionKey): ?string
    {
        if (!\array_key_exists($sessionKey, $_SESSION)) {
            return null;
        }
        if (!\is_string($_SESSION[$sessionKey])) {
            return null;
        }

        return $_SESSION[$sessionKey];
    }

    public function set(string $sessionKey, string $sessionValue): void
    {
        $_SESSION[$sessionKey] = $sessionValue;
    }

    public function remove(string $sessionKey): void
    {
        unset($_SESSION[$sessionKey]);
    }

    public function destroy(): void
    {
        session_destroy();
    }
}
