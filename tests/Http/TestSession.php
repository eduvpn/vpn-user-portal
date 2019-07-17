<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use fkooman\SeCookie\Exception\SessionException;
use fkooman\SeCookie\SessionInterface;

class TestSession implements SessionInterface
{
    /** @var array */
    private $sessionData = [];

    /**
     * Regenerate the session ID.
     */
    public function regenerate(): void
    {
        // NOP
    }

    /**
     * Set session value.
     */
    public function set(string $key, string $value): void
    {
        $this->sessionData[$key] = $value;
    }

    /**
     * Delete session key/value.
     */
    public function delete(string $key): void
    {
        if ($this->has($key)) {
            unset($this->sessionData[$key]);
        }
    }

    /**
     * Test if session key exists.
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->sessionData);
    }

    /**
     * Get session value.
     */
    public function get(string $key): string
    {
        if (!$this->has($key)) {
            throw new SessionException(sprintf('key "%s" not available in session', $key));
        }

        return $this->sessionData[$key];
    }

    /**
     * Destroy the session.
     */
    public function destroy(): void
    {
        $this->sessionData = [];
        $this->regenerate();
    }
}
