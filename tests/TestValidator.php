<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateTimeImmutable;
use fkooman\OAuth\Server\AccessToken;
use fkooman\OAuth\Server\Http\Request;
use fkooman\OAuth\Server\Scope;
use Vpn\Portal\OAuth\ValidatorInterface;

class TestValidator implements ValidatorInterface
{
    public function validate(?Request $request = null): AccessToken
    {
        return new AccessToken(
            'token_id',
            'auth_key',
            'user_id',
            'client_id',
            new Scope('config'),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            'raw_token'
        );
    }
}
