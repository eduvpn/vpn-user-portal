<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SAML\SP\SessionInterface;
use fkooman\SeCookie\Session;

class SeSamlSession implements SessionInterface
{
    private Session $session;

    public function __construct(Session $session)
    {
        $session->start();
        $this->session = $session;
    }

    public function regenerate(): void
    {
        $this->session->regenerate();
    }

    public function get(string $key): ?string
    {
        return $this->session->get($key);
    }

    public function take(string $key): ?string
    {
        if (null !== $sessionValue = $this->session->get($key)) {
            $this->session->remove($key);
        }

        return $sessionValue;
    }

    public function set(string $key, string $value): void
    {
        $this->session->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->session->remove($key);
    }
}
