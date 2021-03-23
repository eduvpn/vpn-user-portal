<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use DateInterval;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SignerInterface;
use fkooman\OAuth\Server\StorageInterface;

/**
 * Class to allow overriding the access_token and refresh_token expiry.
 */
class VpnOAuthServer extends OAuthServer
{
    public function __construct(StorageInterface $storage, ClientDbInterface $clientDb, SignerInterface $signer)
    {
        parent::__construct($storage, $clientDb, $signer);
    }

    public function setAccessTokenExpiry(DateInterval $accessTokenExpiry): void
    {
        $this->accessTokenExpiry = $accessTokenExpiry;
    }

    public function setRefreshTokenExpiry(DateInterval $refreshTokenExpiry): void
    {
        $this->refreshTokenExpiry = $refreshTokenExpiry;
    }
}
