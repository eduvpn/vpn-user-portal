<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTime;

interface CaInterface
{
    /**
     * @return string
     */
    public function caCert();

    /**
     * @param string $commonName
     *
     * @return array{cert:string, key:string, valid_from:int, valid_to:int}
     */
    public function serverCert($commonName);

    /**
     * @param string    $commonName
     * @param \DateTime $expiresAt
     *
     * @return array{cert:string, key:string, valid_from:int, valid_to:int}
     */
    public function clientCert($commonName, DateTime $expiresAt);
}
