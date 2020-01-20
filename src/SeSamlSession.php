<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SAML\SP\Exception\SessionException;
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
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return null !== $this->session->get($key);
    }

    /**
     * @return void
     */
    public function regenerate()
    {
        $this->session->regenerate();
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function get($key)
    {
        if (null === $sessionValue = $this->session->get($key)) {
            throw new SessionException(sprintf('key "%s" not found in session', $key));
        }

        return $sessionValue;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function take($key)
    {
        $sessionValue = $this->get($key);
        $this->session->remove($key);

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
        $this->session->remove($key);
    }
}
