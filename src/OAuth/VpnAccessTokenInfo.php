<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use fkooman\OAuth\Server\AccessTokenInfo;
use fkooman\OAuth\Server\Scope;

class VpnAccessTokenInfo extends AccessTokenInfo
{
    /** @var bool */
    private $isLocal;

    /**
     * @param string $userId
     * @param string $clientId
     * @param bool   $isLocal
     */
    public function __construct($userId, $clientId, Scope $scope, $isLocal)
    {
        parent::__construct($userId, $clientId, $scope);
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
