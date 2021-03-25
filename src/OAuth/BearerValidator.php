<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

use DateTimeImmutable;
use fkooman\Jwt\Keys\EdDSA\PublicKey;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\Exception\InvalidTokenException;
use fkooman\OAuth\Server\Exception\SignerException;
use fkooman\OAuth\Server\Json;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\Scope;
use fkooman\OAuth\Server\StorageInterface;
use fkooman\OAuth\Server\SyntaxValidator;

/**
 * Copy of fkooman/oauth2-server src/BearerValidator.php to support public
 * key crypto (EdDSA JWT) tokens and also remote tokens from other servers.
 */
class BearerValidator
{
    private StorageInterface $storage;

    private ClientDbInterface $clientDb;

    private PublicKey $localPublicKey;

    /** @var array<string,array<string,string>> */
    private array $keyInstanceMapping;

    private DateTimeImmutable $dateTime;

    /**
     * @param array<string,array<string,string>> $keyInstanceMapping
     */
    public function __construct(StorageInterface $storage, ClientDbInterface $clientDb, PublicKey $localPublicKey, array $keyInstanceMapping)
    {
        $this->storage = $storage;
        $this->clientDb = $clientDb;
        $this->localPublicKey = $localPublicKey;
        $this->keyInstanceMapping = $keyInstanceMapping;
        $this->dateTime = new DateTimeImmutable();
    }

    public function validate(string $authorizationHeader): VpnAccessTokenInfo
    {
        try {
            SyntaxValidator::validateBearerToken($authorizationHeader);
            $providedToken = substr($authorizationHeader, 7);

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
            $accessTokenInfo = Json::decode($publicSigner->verify($providedToken));

            // check version
            if (false === OAuthServer::checkTokenVersion($accessTokenInfo)) {
                throw new InvalidTokenException('"access_token" has wrong version');
            }

            // make sure we got an access_token
            if ('access_token' !== $accessTokenInfo['type']) {
                throw new InvalidTokenException(sprintf('expected "access_token", got "%s"', $accessTokenInfo['type']));
            }

            // check access_token expiry
            if ($this->dateTime >= new DateTimeImmutable($accessTokenInfo['expires_at'])) {
                throw new InvalidTokenException('"access_token" expired');
            }

            if (null === $baseUri) {
                // the token was signed by _US_...
                // the client MUST still be there
                if (null === $this->clientDb->get($accessTokenInfo['client_id'])) {
                    throw new InvalidTokenException(sprintf('client "%s" no longer registered', $accessTokenInfo['client_id']));
                }

                // the authorization MUST exist in the DB as well *and* not
                // expired...
                if (!$this->storage->hasAuthorization($accessTokenInfo['auth_key'])) {
                    throw new InvalidTokenException(sprintf('authorization for client "%s" no longer exists', $accessTokenInfo['client_id']));
                }
            }

            $userId = $accessTokenInfo['user_id'];
            if (null !== $baseUri) {
                // append the base_uri in front of the user_id to indicate this is
                // a "remote" user
                $userId = $baseUri.'!!'.$userId;
            }

            return new VpnAccessTokenInfo(
                $userId,
                $accessTokenInfo['client_id'],
                new Scope($accessTokenInfo['scope']),
                // isLocal
                null === $baseUri
            );
        } catch (SignerException $e) {
            throw new InvalidTokenException('invalid "access_token" ('.$e->getMessage().')');
        }
    }
}
