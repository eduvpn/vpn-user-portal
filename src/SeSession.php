<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\FileSessionStorage;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use fkooman\SeCookie\SessionStorageInterface;
use LC\Portal\Http\SessionInterface;

class SeSession implements SessionInterface
{
    private Session $session;

    public function __construct(bool $secureCookie, string $cookiePath, ?SessionStorageInterface $sessionStorage = null)
    {
        $sessionStorage ??= new FileSessionStorage();
        $cookieOptions = $secureCookie ? CookieOptions::init() : CookieOptions::init()->withoutSecure();
        $this->session = new Session(
            SessionOptions::init(),
            $cookieOptions->withMaxAge(60 * 60 * 24 * 90)->withSameSiteStrict()->withPath($cookiePath),
            $sessionStorage
        );
        $this->session->start();
    }

    public function regenerate(): void
    {
        $this->session->regenerate();
    }

    public function get(string $sessionKey): ?string
    {
        return $this->session->get($sessionKey);
    }

    public function set(string $sessionKey, string $sessionValue): void
    {
        $this->session->set($sessionKey, $sessionValue);
    }

    public function remove(string $sessionKey): void
    {
        $this->session->remove($sessionKey);
    }

    public function destroy(): void
    {
        $this->session->destroy();
    }
}
