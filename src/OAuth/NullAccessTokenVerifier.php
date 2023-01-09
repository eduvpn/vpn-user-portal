<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OAuth;

use fkooman\OAuth\Server\AccessToken;
use fkooman\OAuth\Server\AccessTokenVerifierInterface;

/**
 * This class does nothing. We use it instead of LocalAccessTokenVerifier
 * in the "Guest Access" scenario where we don't care whether the OAuth
 * client still exists, or whether the authorization is there.
 * We'll handle the authorization in the "GuestApiService" instead.
 */
class NullAccessTokenVerifier implements AccessTokenVerifierInterface
{
    public function verify(AccessToken $accessToken): void
    {
        // NOP
    }
}
