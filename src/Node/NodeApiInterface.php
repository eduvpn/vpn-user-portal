<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Node;

use DateTime;

interface NodeApiInterface
{
    /**
     * @param string    $profileId
     * @param string    $commonName
     * @param string    $ipFour
     * @param string    $ipSix
     * @param \DateTime $connectedAt
     *
     * @return void
     */
    public function connect($profileId, $commonName, $ipFour, $ipSix, DateTime $connectedAt);

    /**
     * @param string    $profileId
     * @param string    $commonName
     * @param string    $ipFour
     * @param string    $ipSix
     * @param \DateTime $connectedAt
     * @param \DateTime $disconnectedAt
     * @param int       $bytesTransferred
     *
     * @return void
     */
    public function disconnect($profileId, $commonName, $ipFour, $ipSix, DateTime $connectedAt, DateTime $disconnectedAt, $bytesTransferred);

    /**
     * @param string $commonName
     *
     * @return array<string, string>
     */
    public function addServerCertificate($commonName);

    /**
     * @return array<string, \LC\Portal\Config\ProfileConfig>
     */
    public function getProfileList();
}
