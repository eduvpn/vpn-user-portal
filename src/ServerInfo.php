<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\Jwt\Keys\EdDSA\PublicKey as OAuthPublicKey;
use LC\Portal\CA\CaInterface;

class ServerInfo
{
    private CaInterface $ca;
    private OAuthPublicKey $oauthPublicKey;

    public function __construct(CaInterface $ca, OAuthPublicKey $oauthPublicKey)
    {
        $this->ca = $ca;
        $this->oauthPublicKey = $oauthPublicKey;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
    }

    public function oauthPublicKey(): OAuthPublicKey
    {
        return $this->oauthPublicKey;
    }
}
