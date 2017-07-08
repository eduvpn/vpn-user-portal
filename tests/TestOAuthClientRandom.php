<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\RandomInterface;

class TestOAuthClientRandom implements RandomInterface
{
    /** @var int */
    private $counter = 0;

    /**
     * @param int  $length
     * @param bool $rawBytes
     *
     * @return string
     */
    public function get($length, $rawBytes = false)
    {
        return sprintf('random_%d', $this->counter++);
    }
}
