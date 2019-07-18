<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use fkooman\SAML\SP\SessionInterface;
use fkooman\SeCookie\Session;

class SeCookieSession implements SessionInterface
{
    /** @var \fkooman\SeCookie\Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->session->has($key);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function get($key)
    {
        return $this->session->get($key);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function take($key)
    {
        $sessionValue = $this->session->get($key);
        $this->session->delete($key);

        return $sessionValue;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function set($key, $value)
    {
        $this->session->set($key, $value);
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function delete($key)
    {
        $this->session->delete($key);
    }
}
