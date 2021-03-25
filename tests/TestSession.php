<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\Http\SessionInterface;

class TestSession implements SessionInterface
{
    /** @var array */
    private $sessionData = [];

    public function regenerate(): void
    {
        // NOP
    }

    /**
     * @param string $sessionKey
     * @param string $sessionValue
     */
    public function set($sessionKey, $sessionValue): void
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    /**
     * @param string $sessionKey
     */
    public function remove($sessionKey): void
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

    public function destroy(): void
    {
        $this->sessionData = [];
    }
}
