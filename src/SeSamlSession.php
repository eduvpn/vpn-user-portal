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
    /** @var \fkooman\SeCookie\Session */
    private $session;

    public function __construct(Session $session)
    {
        $session->start();
        $this->session = $session;
    }

    public function regenerate(): void
    {
        $this->session->regenerate();
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function get($key)
    {
        return $this->session->get($key);
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function take($key)
    {
        if (null !== $sessionValue = $this->session->get($key)) {
            $this->session->remove($key);
        }

        return $sessionValue;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function set($key, $value): void
    {
        $this->session->set($key, $value);
    }

    /**
     * @param string $key
     */
    public function remove($key): void
    {
        $this->session->remove($key);
    }
}
