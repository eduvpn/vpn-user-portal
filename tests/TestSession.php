<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Common\Http\SessionInterface;
use RuntimeException;

class TestSession implements SessionInterface
{
    /** @var array */
    private $sessionData = [];

    /**
     * @return string
     */
    public function id()
    {
        return '12345';
    }

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
    public function setString($sessionKey, $sessionValue)
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    /**
     * @param string $sessionKey
     *
     * @return void
     */
    public function delete($sessionKey)
    {
        if ($this->has($sessionKey)) {
            unset($this->sessionData[$sessionKey]);
        }
    }

    /**
     * @param string $sessionKey
     *
     * @return bool
     */
    public function has($sessionKey)
    {
        return \array_key_exists($sessionKey, $this->sessionData);
    }

    /**
     * @param string $sessionKey
     *
     * @return string
     */
    public function getString($sessionKey)
    {
        if (!$this->has($sessionKey)) {
            throw new RuntimeException(sprintf('key "%s" not available in session', $sessionKey));
        }

        return $this->sessionData[$sessionKey];
    }

    /**
     * @param string $sessionKey
     *
     * @return array<string>
     */
    public function getStringArray($sessionKey)
    {
        if (!$this->has($sessionKey)) {
            throw new RuntimeException(sprintf('key "%s" not available in session', $sessionKey));
        }

        return $this->sessionData[$sessionKey];
    }

    /**
     * @param string        $sessionKey
     * @param array<string> $sessionValue
     *
     * @return void
     */
    public function setStringArray($sessionKey, array $sessionValue)
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    /**
     * @param string $sessionKey
     *
     * @return bool
     */
    public function getBool($sessionKey)
    {
        if (!$this->has($sessionKey)) {
            throw new RuntimeException(sprintf('key "%s" not available in session', $sessionKey));
        }

        return $this->sessionData[$sessionKey];
    }

    /**
     * @param string $sessionKey
     * @param bool   $sessionValue
     *
     * @return void
     */
    public function setBool($sessionKey, $sessionValue)
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->sessionData = [];
        $this->regenerate();
    }
}
