<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OAuth;

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
    public function __construct(StorageInterface $storage, ClientDbInterface $clientDb, SignerInterface $signer, DateInterval $accessTokenExpiry)
    {
        parent::__construct($storage, $clientDb, $signer);
        $this->accessTokenExpiry = $accessTokenExpiry;
    }
}
