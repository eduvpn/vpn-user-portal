<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal\OAuth;

use DateInterval;
use DateTime;
use fkooman\Jwt\Keys\EdDSA\PublicKey;
use fkooman\OAuth\Server\ClientDbInterface;
use fkooman\OAuth\Server\Exception\InvalidTokenException;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\Scope;
use fkooman\OAuth\Server\StorageInterface;
use fkooman\OAuth\Server\SyntaxValidator;
use LetsConnect\Common\HttpClient\ServerClient;
use ParagonIE\ConstantTime\Binary;

class BearerValidator
{
    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateInterval */
    private $sessionExpiry;

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
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     * @param \DateInterval                               $sessionExpiry
     * @param \fkooman\OAuth\Server\StorageInterface      $storage
     * @param \fkooman\OAuth\Server\ClientDbInterface     $clientDb
     * @param \fkooman\Jwt\Keys\EdDSA\PublicKey           $localPublicKey
     * @param array<string,array<string,string>>          $keyInstanceMapping
     */
    public function __construct(ServerClient $serverClient, DateInterval $sessionExpiry, StorageInterface $storage, ClientDbInterface $clientDb, PublicKey $localPublicKey, array $keyInstanceMapping)
    {
        $this->serverClient = $serverClient;
        $this->sessionExpiry = $sessionExpiry;
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

        // default expiresAt is NOW+sessionExpiry
        $expiresAt = date_add(clone $this->dateTime, $this->sessionExpiry);
        $permissionList = [];

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

            // make sure user authenticated recently enough
            // XXX server can also return null, then this fails!
            $lastAuthenticatedAt = new DateTime($this->serverClient->getRequireString('user_last_authenticated_at', ['user_id' => $accessTokenInfo['user_id']]));

            $expiresAt = date_add(clone $lastAuthenticatedAt, $this->sessionExpiry);
            if ($this->dateTime > $expiresAt) {
                // we are not allowed to accept this access_token any longer
                // user MUST authenticate again
                $this->storage->deleteAuthorization($accessTokenInfo['auth_key']);

                throw new InvalidTokenException(sprintf('authorization for this user expired'));
            }

            // get the permissionList as well
            $permissionList = $this->serverClient->getRequireArray('user_permission_list', ['user_id' => $accessTokenInfo['user_id']]);
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
            $permissionList,
            $expiresAt
        );
    }
}
