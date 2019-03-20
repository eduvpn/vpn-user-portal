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
use fkooman\OAuth\Server\Json;
use fkooman\OAuth\Server\ResourceOwner;
use fkooman\OAuth\Server\Scope;

class VpnAccessTokenInfo extends AccessTokenInfo
{
    /** @var bool */
    private $isLocal;

    /**
     * @param \fkooman\OAuth\Server\ResourceOwner $resourceOwner
     * @param string                              $clientId
     * @param Scope                               $scope
     * @param \DateTime                           $authzExpiresAt
     * @param bool                                $isLocal
     */
    public function __construct(ResourceOwner $resourceOwner, $clientId, Scope $scope, DateTime $authzExpiresAt, $isLocal)
    {
        parent::__construct($resourceOwner, $clientId, $scope, $authzExpiresAt);
        $this->isLocal = $isLocal;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->getResourceOwner()->getUserId();
    }

    /**
     * @return array<string>
     */
    public function getPermissionList()
    {
        if (null === $extData = $this->getResourceOwner()->getExtData()) {
            return [];
        }

        $permissionList = Json::decode($extData);
        if (!\is_array($permissionList)) {
            return [];
        }

        return $permissionList;
    }

    /**
     * @return bool
     */
    public function getIsLocal()
    {
        return $this->isLocal;
    }
}
