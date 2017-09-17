<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Server\RandomInterface;

class TestOAuthServerRandom implements RandomInterface
{
    /** @var int */
    private $counter = 1;

    /**
     * Get a randomly generated crypto secure string.
     *
     * @param int $length
     */
    public function get($length)
    {
        return sprintf('random_%d', $this->counter++);
    }
}
