<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SAML\SP\SessionInterface;
use fkooman\SeCookie\Session;

class SeSamlSession implements SessionInterface
{
    /** var \fkooman\SeCookie\Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @return void
     */
    public function regenerate()
    {
        $this->session->regenerate(true);
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function get($key)
    {
        if (!$this->session->has($key)) {
            return null;
        }

        return $this->session->get($key);
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function take($key)
    {
        if (!$this->session->has($key)) {
            return null;
        }
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
