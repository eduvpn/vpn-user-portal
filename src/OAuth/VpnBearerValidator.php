<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OAuth;

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\LocalAccessTokenVerifier;
use fkooman\OAuth\Server\SignerInterface;
use fkooman\OAuth\Server\StorageInterface;

class VpnBearerValidator extends BearerValidator
{
    public function __construct(SignerInterface $signer, ClientDbInterface $clientDb, StorageInterface $storage)
    {
        parent::__construct(
            $signer,
            new LocalAccessTokenVerifier(
                $clientDb,
                $storage
            )
        );
    }
}
