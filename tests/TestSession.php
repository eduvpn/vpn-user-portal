<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\SeCookie\Exception\SessionException;
use fkooman\SeCookie\SessionInterface;

class TestSession implements SessionInterface
{
    /** @var array */
    private $sessionData = [];

    /**
     * Get the session ID.
     *
     * @return string
     */
    public function id()
    {
        return '12345';
    }

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession
     */
    public function regenerate($deleteOldSession = false)
    {
        // NOP
    }

    /**
     * Set session value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->sessionData[$key] = $value;
    }

    /**
     * Delete session key/value.
     *
     * @param string $key
     */
    public function delete($key)
    {
        if ($this->has($key)) {
            unset($this->sessionData[$key]);
        }
    }

    /**
     * Test if session key exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->sessionData);
    }

    /**
     * Get session value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            throw new SessionException(sprintf('key "%s" not available in session', $key));
        }

        return $this->sessionData[$key];
    }

    /**
     * Empty the session.
     */
    public function destroy()
    {
        $this->sessionData = [];
        $this->regenerate(true);
    }
}
