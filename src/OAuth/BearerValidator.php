<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth;

use DateTime;
use fkooman\Jwt\Keys\EdDSA\PublicKey;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\Exception\InvalidTokenException;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\ResourceOwner;
use fkooman\OAuth\Server\Scope;
use fkooman\OAuth\Server\StorageInterface;
use fkooman\OAuth\Server\SyntaxValidator;
use ParagonIE\ConstantTime\Binary;

class BearerValidator
{
    /** @var StorageInterface */
    private $storage;

    /** @var ClientDbInterface */
    private $clientDb;

    /** @var \fkooman\Jwt\Keys\EdDSA\PublicKey */
    private $localPublicKey;

    /** @var array<string,array<string,string>> */
    private $keyInstanceMapping;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param \fkooman\OAuth\Server\StorageInterface  $storage
     * @param \fkooman\OAuth\Server\ClientDbInterface $clientDb
     * @param \fkooman\Jwt\Keys\EdDSA\PublicKey       $localPublicKey
     * @param array<string,array<string,string>>      $keyInstanceMapping
     */
    public function __construct(StorageInterface $storage, ClientDbInterface $clientDb, PublicKey $localPublicKey, array $keyInstanceMapping)
    {
        $this->storage = $storage;
        $this->clientDb = $clientDb;
        $this->localPublicKey = $localPublicKey;
        $this->keyInstanceMapping = $keyInstanceMapping;
        $this->dateTime = new DateTime();
    }

    /**
     * @param DateTime $dateTime
     *
     * @return void
     */
    public function setDateTime(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    /**
     * @param string $authorizationHeader
     *
     * @return VpnAccessTokenInfo
     */
    public function validate($authorizationHeader)
    {
        SyntaxValidator::validateBearerToken($authorizationHeader);
        $providedToken = Binary::safeSubstr($authorizationHeader, 7);

        // extract the key ID from the received Bearer token
        if (null === $keyId = PublicSigner::extractKid($providedToken)) {
            throw new InvalidTokenException('missing "kid"');
        }

        $baseUri = null;
        $publicKey = $this->localPublicKey;

        if ($keyId !== PublicSigner::calculateKeyId($this->localPublicKey)) {
            // NOT a local key, check the keyInstanceMapping if we know this key ID
            if (!\array_key_exists($keyId, $this->keyInstanceMapping)) {
                // we do not know this key ID...
                throw new InvalidTokenException('unknown "kid", we do not trust this key');
            }
            $publicKey = PublicKey::fromEncodedString($this->keyInstanceMapping[$keyId]['public_key']);
            $baseUri = $this->keyInstanceMapping[$keyId]['base_uri'];
        }

        $publicSigner = new PublicSigner($publicKey);
        if (false === $accessTokenInfo = $publicSigner->verify($providedToken)) {
            throw new InvalidTokenException('"access_token" has invalid signature');
        }

        // check version
        if (false === OAuthServer::checkTokenVersion($accessTokenInfo)) {
            throw new InvalidTokenException('"access_token" has wrong version');
        }

        // make sure we got an access_token
        if ('access_token' !== $accessTokenInfo['type']) {
            throw new InvalidTokenException(sprintf('expected "access_token", got "%s"', $accessTokenInfo['type']));
        }

        // check access_token expiry
        if ($this->dateTime >= new DateTime($accessTokenInfo['expires_at'])) {
            throw new InvalidTokenException('"access_token" expired');
        }

        if (null === $baseUri) {
            // the token was signed by _US_...
            // the client MUST still be there
            if (false === $this->clientDb->get($accessTokenInfo['client_id'])) {
                throw new InvalidTokenException(sprintf('client "%s" no longer registered', $accessTokenInfo['client_id']));
            }

            // the authorization MUST exist in the DB as well, otherwise it was
            // revoked...
            if (!$this->storage->hasAuthorization($accessTokenInfo['auth_key'])) {
                throw new InvalidTokenException(sprintf('authorization for client "%s" no longer exists', $accessTokenInfo['client_id']));
            }
        }

        $resourceOwner = ResourceOwner::fromEncodedString($accessTokenInfo['resource_owner']);
        $userId = $resourceOwner->getUserId();
        if (null !== $baseUri) {
            // append the base_uri in front of the user_id to indicate this is
            // a "remote" user
            $userId = $baseUri.'!!'.$userId;
        }

        return new VpnAccessTokenInfo(
            new ResourceOwner($userId),
            $accessTokenInfo['client_id'],
            new Scope($accessTokenInfo['scope']),
            new DateTime($accessTokenInfo['authz_expires_at']),
            null === $baseUri   // isLocal
        );
    }
}
