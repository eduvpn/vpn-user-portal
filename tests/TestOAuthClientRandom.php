<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\RandomInterface;

class TestOAuthClientRandom implements RandomInterface
{
    /** @var int */
    private $counter = 0;

    /**
     * {@inheritdoc}
     */
    public function getHex($length)
    {
        return sprintf('random_%d', $this->counter++);
    }

    public function getRaw($length)
    {
        return sprintf('random_%d', $this->counter++);
    }
}
