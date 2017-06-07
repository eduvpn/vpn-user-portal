<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
