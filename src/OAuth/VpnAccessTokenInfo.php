<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth;

use DateTime;
use fkooman\OAuth\Server\AccessTokenInfo;
use fkooman\OAuth\Server\Scope;

class VpnAccessTokenInfo extends AccessTokenInfo
{
    /** @var bool */
    private $isLocal;

    /**
     * @param string    $userId
     * @param string    $clientId
     * @param Scope     $scope
     * @param \DateTime $authzTime
     * @param bool      $isLocal
     */
    public function __construct($userId, $clientId, Scope $scope, DateTime $authzTime, $isLocal)
    {
        parent::__construct($userId, $clientId, $scope, $authzTime);
        $this->isLocal = $isLocal;
    }

    /**
     * @return bool
     */
    public function getIsLocal()
    {
        return $this->isLocal;
    }
}
