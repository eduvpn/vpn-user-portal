<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\MemcachedSessionStorage;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use LC\Portal\SessionConfig;
use Memcached;
use RuntimeException;

class SeSession implements SessionInterface
{
    private Session $session;

    public function __construct(CookieOptions $cookieOptions, SessionConfig $sessionConfig)
    {
        // default session storage is "FileSessionStorage"
        $sessionStorage = null;
        if ($sessionConfig->useMemcached()) {
            if (!\extension_loaded('memcached')) {
                throw new RuntimeException('"memcached" PHP extension not available');
            }
            $m = new Memcached();
            foreach ($sessionConfig->memcachedServerList() as $memCachedServer) {
                $m->addServer($memCachedServer['h'], $memCachedServer['p']);
            }
            $sessionStorage = new MemcachedSessionStorage($m);
        }
        $this->session = new Session(
            SessionOptions::init(),
            $cookieOptions,
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
