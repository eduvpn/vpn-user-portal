<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\MemcacheSessionStorage;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use Vpn\Portal\Config;

class SeSession implements SessionInterface
{
    private Session $session;

    public function __construct(CookieOptions $cookieOptions, Config $config)
    {
        $sessionStorage = null;
        if ('MemcacheSessionModule' === $config->sessionModule()) {
            $sessionStorage = new MemcacheSessionStorage($config->memcacheSessionConfig()->serverList());
        }
        $this->session = new Session(
            SessionOptions::init(),
            $cookieOptions,
            $sessionStorage
        );
        $this->session->start();
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
