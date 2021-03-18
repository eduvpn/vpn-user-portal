<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Common\Http\SessionInterface;

class TestSession implements SessionInterface
{
    /** @var array */
    private $sessionData = [];

    /**
     * @return void
     */
    public function regenerate()
    {
        // NOP
    }

    /**
     * @param string $sessionKey
     * @param string $sessionValue
     *
     * @return void
     */
    public function set($sessionKey, $sessionValue)
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    /**
     * @param string $sessionKey
     *
     * @return void
     */
    public function remove($sessionKey)
    {
        if (\array_key_exists($sessionKey, $this->sessionData)) {
            unset($this->sessionData[$sessionKey]);
        }
    }

    /**
     * @param string $sessionKey
     *
     * @return string|null
     */
    public function get($sessionKey)
    {
        if (!\array_key_exists($sessionKey, $this->sessionData)) {
            return null;
        }

        return $this->sessionData[$sessionKey];
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->sessionData = [];
    }
}
