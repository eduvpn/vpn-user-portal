<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class RadiusServerConfig extends Config
{
    public function getHost(): string
    {
        return $this->requireString('host');
    }

    public function getSecret(): string
    {
        return $this->requireString('secret');
    }

    public function getPort(): int
    {
        if (null === $port = $this->optionalInt('port')) {
            return 1812;
        }

        return $port;
    }
}
