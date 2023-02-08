<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DomainException;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\FileSessionStorage;
use fkooman\SeCookie\JsonSerializer;
use fkooman\SeCookie\MemcacheSessionStorage;
use fkooman\SeCookie\Session;
use fkooman\SeCookie\SessionOptions;
use Vpn\Portal\Cfg\Config;

class SeSession implements SessionInterface
{
    private Session $session;

    public function __construct(CookieOptions $cookieOptions, Config $config)
    {
        switch($config->sessionModule()) {
            case 'FileSessionModule':
                $sessionStorage = new FileSessionStorage(null, new JsonSerializer());
                break;
            case 'MemcacheSessionModule':
                $sessionStorage = new MemcacheSessionStorage(
                    $config->memcacheSessionConfig()->serverList(),
                    new JsonSerializer()
                );
                break;
            default:
                throw new DomainException(sprintf('session module "%s" not supported', $config->sessionModule()));
        }

        $this->session = new Session(
            SessionOptions::init()->withExpiresIn($config->browserSessionExpiry()),
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

    public function stop(): void
    {
        $this->session->stop();
    }
}
