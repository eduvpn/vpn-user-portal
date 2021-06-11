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
use fkooman\OAuth\Server\AccessToken;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\Exception\InvalidTokenException;
use fkooman\OAuth\Server\Exception\SignerException;
use fkooman\OAuth\Server\StorageInterface;
use fkooman\OAuth\Server\SyntaxValidator;
use LC\Portal\Dt;

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
        $this->dateTime = Dt::get();
    }

    public function validate(string $authorizationHeader): VpnAccessToken
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
            $accessToken = AccessToken::fromJson($publicSigner->verify($providedToken));

            // check access_token expiry
            if ($this->dateTime >= $accessToken->expiresAt()) {
                throw new InvalidTokenException('"access_token" expired');
            }

            if (null === $baseUri) {
                // the token was signed by _US_...
                // the client MUST still be there
                if (null === $this->clientDb->get($accessToken->clientId())) {
                    throw new InvalidTokenException(sprintf('client "%s" no longer registered', $accessToken->clientId()));
                }

                // the authorization MUST exist in the DB as well *and* not
                // expired...
                if (null === $this->storage->getAuthorization($accessToken->authKey())) {
                    throw new InvalidTokenException(sprintf('authorization for client "%s" no longer exists', $accessToken->clientId()));
                }
            }

            $userId = $accessToken->userId();
            if (null !== $baseUri) {
                // append the base_uri in front of the user_id to indicate this is
                // a "remote" user
                $userId = $baseUri.'!!'.$userId;
            }

            return new VpnAccessToken(
                $accessToken,
                // isLocal
                null === $baseUri
            );
        } catch (SignerException $e) {
            throw new InvalidTokenException('invalid "access_token" ('.$e->getMessage().')');
        }
    }
}
