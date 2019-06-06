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
    /**
     * @return string
     */
    public function getHost()
    {
        return $this->requireString('host');
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->requireString('secret');
    }

    /**
     * @return int
     */
    public function getPort()
    {
        if (null === $port = $this->optionalInt('port')) {
            return 1812;
        }

        return $port;
    }
}
