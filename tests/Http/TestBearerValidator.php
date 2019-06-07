<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use fkooman\OAuth\Server\Scope;
use LC\Portal\OAuth\BearerValidatorInterface;
use LC\Portal\OAuth\VpnAccessTokenInfo;

class TestBearerValidator implements BearerValidatorInterface
{
    public function validate(string $authorizationHeader): VpnAccessTokenInfo
    {
        return new VpnAccessTokenInfo('foo', 'org.eduvpn.app', new Scope('config'), true);
    }
}
