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
    /** @var \fkooman\SeCookie\Session */
    private $session;

    public function __construct(Session $session)
    {
        $session->start();
        $this->session = $session;
    }

    /**
     * @return void
     */
    public function regenerate()
    {
        $this->session->regenerate();
    }

    /**
     * @param string $sessionKey
     *
     * @return string|null
     */
    public function get($sessionKey)
    {
        return $this->session->get($sessionKey);
    }

    /**
     * @param string $sessionKey
     * @param string $sessionValue
     *
     * @return void
     */
    public function set($sessionKey, $sessionValue)
    {
        $this->session->set($sessionKey, $sessionValue);
    }

    /**
     * @param string $sessionKey
     *
     * @return void
     */
    public function remove($sessionKey)
    {
        $this->session->remove($sessionKey);
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->session->destroy();
    }
}
