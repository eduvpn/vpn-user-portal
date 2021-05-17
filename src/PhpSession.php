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

class PhpSession implements SessionInterface
{
    const SESSION_NAME = 'SID';

    const SESSION_EXPIRY = 1800; // 30 minutes

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

        if (!\array_key_exists('__expires_at', $_SESSION)) {
            // new session
            $_SESSION['__expires_at'] = time() + self::SESSION_EXPIRY;

            return;
        }
        if ($_SESSION['__expires_at'] > time()) {
            // existing non-expired session
            return;
        }
        // expired session
        $this->destroy();
        $this->regenerate();
    }

    public function regenerate(): void
    {
        session_regenerate_id();
        $_SESSION['__expires_at'] = time() + self::SESSION_EXPIRY;
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
        $_SESSION = [];
    }
}
