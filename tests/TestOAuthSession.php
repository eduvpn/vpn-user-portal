<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\Exception\SessionException;
use fkooman\OAuth\Client\SessionInterface;

class TestOAuthSession implements SessionInterface
{
    /** @var array */
    private $data = [];

    /**
     * Get value, delete key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function take($key)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new SessionException(sprintf('key "%s" not found in session', $key));
        }
        $value = $this->data[$key];
        unset($this->data[$key]);

        return $value;
    }

    /**
     * Set key to value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
}
