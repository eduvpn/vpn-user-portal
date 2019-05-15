<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

interface ServerManagerInterface
{
    /**
     * @return array<string,array>
     */
    public function connections();

    /**
     * @param string $commonName
     *
     * @return int
     */
    public function kill($commonName);
}
