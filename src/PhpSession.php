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
use RuntimeException;

class PhpSession implements SessionInterface
{
    public function __construct(bool $secureCookie)
    {
        $sessionOptions = [
            'cookie_samesite' => 'Strict',
            'cookie_secure' => $secureCookie,
            'cookie_httponly' => true,
        ];

        if (false === session_start($sessionOptions)) {
            // XXX better exception type
            throw new RuntimeException('unable to start session');
        }
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
