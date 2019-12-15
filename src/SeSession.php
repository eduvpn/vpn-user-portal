<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\SeCookie\Session;
use LC\Common\Http\SessionInterface;

class SeSession implements SessionInterface
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
     * @param string $sessionKey
     *
     * @return bool
     */
    public function has($sessionKey)
    {
        return $this->session->has($sessionKey);
    }

    /**
     * @param string $sessionKey
     *
     * @return string
     */
    public function getString($sessionKey)
    {
        return $this->session->get($sessionKey);
    }

    /**
     * @param string $sessionKey
     * @param string $sessionValue
     *
     * @return void
     */
    public function setString($sessionKey, $sessionValue)
    {
        $this->session->set($sessionKey, $sessionValue);
    }

    /**
     * @param string $sessionKey
     *
     * @return array<string>
     */
    public function getStringArray($sessionKey)
    {
        return $this->session->get($sessionKey);
    }

    /**
     * @param string        $sessionKey
     * @param array<string> $sessionValue
     *
     * @return void
     */
    public function setStringArray($sessionKey, array $sessionValue)
    {
        $this->session->set($sessionKey, $sessionValue);
    }

    /**
     * @param string $sessionKey
     *
     * @return bool
     */
    public function getBool($sessionKey)
    {
        return $this->session->get($sessionKey);
    }

    /**
     * @param string $sessionKey
     * @param bool   $sessionValue
     *
     * @return void
     */
    public function setBool($sessionKey, $sessionValue)
    {
        $this->session->set($sessionKey, $sessionValue);
    }

    /**
     * @param string $sessionKey
     *
     * @return void
     */
    public function delete($sessionKey)
    {
        $this->session->delete($sessionKey);
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->session->destroy();
    }
}
