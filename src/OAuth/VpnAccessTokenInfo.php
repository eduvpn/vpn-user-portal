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
    /** @var array<string> */
    private $permissionList;

    /** @var \DateTime */
    private $expiresAt;

    /**
     * @param string        $userId
     * @param string        $clientId
     * @param Scope         $scope
     * @param array<string> $permissionList
     * @param \DateTime     $expiresAt
     */
    public function __construct($userId, $clientId, Scope $scope, array $permissionList, DateTime $expiresAt)
    {
        parent::__construct($userId, $clientId, $scope);
        $this->permissionList = $permissionList;
        $this->expiresAt = $expiresAt;
    }

    /**
     * @return array<string>
     */
    public function getPermissionList()
    {
        return $this->permissionList;
    }

    /**
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }
}
