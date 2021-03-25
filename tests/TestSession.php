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
    private array $sessionData = [];

    public function regenerate(): void
    {
        // NOP
    }

    public function set(string $sessionKey, string $sessionValue): void
    {
        $this->sessionData[$sessionKey] = $sessionValue;
    }

    public function remove(string $sessionKey): void
    {
        if (\array_key_exists($sessionKey, $this->sessionData)) {
            unset($this->sessionData[$sessionKey]);
        }
    }

    public function get(string $sessionKey): ?string
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
