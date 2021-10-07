<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\SeCookie\Session;

class SeSession implements SessionInterface
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
