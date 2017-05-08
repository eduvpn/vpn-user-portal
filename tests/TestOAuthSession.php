<?php

/**
 * Copyright (c) 2016, 2017 François Kooman <fkooman@tuxed.net>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\Exception\SessionException;
use fkooman\OAuth\Client\SessionInterface;

class TestOAuthSession implements SessionInterface
{
    /** @var array */
    private $data = [];

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @param string $key
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            throw new SessionException(sprintf('key "%s" not found in session', $key));
        }

        return $this->data[$key];
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     */
    public function del($key)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new SessionException(sprintf('key "%s" not found in session', $key));
        }
        unset($this->data[$key]);
    }
}
