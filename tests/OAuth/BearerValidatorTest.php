<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth\Tests;

use DateInterval;
use DateTime;
use fkooman\Jwt\Keys\EdDSA\SecretKey;
use fkooman\OAuth\Server\OAuthServer;
use LC\Portal\OAuth\BearerValidator;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class BearerValidatorTest extends TestCase
{
    /** @var \LC\Portal\OAuth\BearerValidator */
    private $bearerValidator;

    /** @var \fkooman\Jwt\Keys\EdDSA\SecretKey */
    private $secretKey;

    /** @var \fkooman\Jwt\Keys\EdDSA\SecretKey */
    private $remoteSecretKey;

    /** @var \DateTime */
    private $dateTime;

    protected function setUp(): void
    {
        $this->dateTime = new DateTime('2018-01-01');

        $storage = new Storage(
            new PDO('sqlite::memory:'),
            \dirname(__DIR__, 2).'/schema'
        );
        $storage->init();
        $storage->storeAuthorization('foo', 'org.letsconnect-vpn.app.windows', 'config', 'random_1');
        $clientDb = new ClientDb();
        $this->secretKey = SecretKey::generate();
        $this->remoteSecretKey = SecretKey::generate();
        $keyInstanceMapping = [
            PublicSigner::calculateKeyId($this->remoteSecretKey->getPublicKey()) => [
                'base_uri' => 'https://vpn.example.org',
                'public_key' => $this->remoteSecretKey->getPublicKey()->encode(),
            ],
        ];

        $this->bearerValidator = new BearerValidator(
            $storage,
            $clientDb,
            $this->secretKey->getPublicKey(),
            $keyInstanceMapping
        );
        $this->bearerValidator->setDateTime($this->dateTime);
    }

    public function testLocalToken(): void
    {
        $signer = new PublicSigner($this->secretKey->getPublicKey(), $this->secretKey);
        $bearerToken = $signer->sign(
            [
                'v' => OAuthServer::TOKEN_VERSION,
                'type' => 'access_token',
                'auth_key' => 'random_1', // to bind it to the authorization
                'user_id' => 'foo',
                'client_id' => 'org.letsconnect-vpn.app.windows',
                'scope' => 'config',
                'expires_at' => date_add(clone $this->dateTime, new DateInterval('PT1H'))->format(DateTime::ATOM),
            ]
        );

        $accessTokenInfo = $this->bearerValidator->validate('Bearer '.$bearerToken);
        $this->assertSame('foo', $accessTokenInfo->getUserId());
        $this->assertSame('org.letsconnect-vpn.app.windows', $accessTokenInfo->getClientId());
        $this->assertSame('config', (string) $accessTokenInfo->getScope());
        $this->assertTrue($accessTokenInfo->getIsLocal());
    }

    public function testRemoteToken(): void
    {
        $signer = new PublicSigner($this->remoteSecretKey->getPublicKey(), $this->remoteSecretKey);
        $bearerToken = $signer->sign(
            [
                'v' => OAuthServer::TOKEN_VERSION,
                'type' => 'access_token',
                'auth_key' => 'remote_random_1', // to bind it to the authorization
                'user_id' => 'foo',
                'client_id' => 'org.letsconnect-vpn.app.windows',
                'scope' => 'config',
                'expires_at' => date_add(clone $this->dateTime, new DateInterval('PT1H'))->format(DateTime::ATOM),
            ]
        );
        $accessTokenInfo = $this->bearerValidator->validate('Bearer '.$bearerToken);
        $this->assertSame('https://vpn.example.org!!foo', $accessTokenInfo->getUserId());
        $this->assertSame('org.letsconnect-vpn.app.windows', $accessTokenInfo->getClientId());
        $this->assertSame('config', (string) $accessTokenInfo->getScope());
        $this->assertFalse($accessTokenInfo->getIsLocal());
    }
}
