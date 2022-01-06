<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use fkooman\OAuth\Server\AccessToken;
use fkooman\OAuth\Server\Http\Request;
use fkooman\OAuth\Server\Scope;
use Vpn\Portal\OAuth\ValidatorInterface;

class TestValidator implements ValidatorInterface
{
    public function validate(?Request $request = null): AccessToken
    {
        $dateTime = new DateTimeImmutable('2022-01-01T09:00:00+00:00');
        $expiresAt = $dateTime->add(new DateInterval('PT1H'));
        $authorizationExpiresAt = $dateTime->add(new DateInterval('P90D'));

        return new AccessToken(
            'token_id',
            'auth_key',
            'user_id',
            'client_id',
            new Scope('config'),
            $expiresAt,
            $authorizationExpiresAt,
            'raw_token'
        );
    }
}
