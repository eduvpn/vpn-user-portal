<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use fkooman\OAuth\Server\PublicKeySourceInterface;
use Vpn\Portal\Cfg\ApiConfig;
use Vpn\Portal\Crypto\VerifierInterface;
use Vpn\Portal\HttpClient\HttpClientInterface;
use Vpn\Portal\HttpClient\HttpClientRequest;

/**
 * Fetch "server_list.json" and extract public key or "baseUrl" from it for
 * use with "Guest Access".
 */
class ServerList implements PublicKeySourceInterface
{
    private string $dataDir;
    private ApiConfig $apiConfig;

    public function __construct(string $dataDir, ApiConfig $apiConfig)
    {
        $this->dataDir = $dataDir;
        $this->apiConfig = $apiConfig;
    }

    /**
     * Fetch, or update our local copy of "server_list.json".
     */
    public function update(HttpClientInterface $httpClient, VerifierInterface $signatureVerifier): void
    {
        $serverListUrl = $this->apiConfig->guestAccessServerListUrl();
        $serverListSignatureUrl = $this->apiConfig->guestAccessServerListSignatureUrl();

        // 1. check we already have server_list.json
        // 2. extract version, or 0 if we don't have it
        $fileVersion = 0;

        // we encode the URL to make it safe to store it on the file system
        $serverListFile = $this->dataDir.'/'.Base64UrlSafe::encodeUnpadded($serverListUrl);
        if (FileIO::exists($serverListFile)) {
            $jsonData = Json::decode(FileIO::read($serverListFile));
            if (\array_key_exists('v', $jsonData) && \is_int($jsonData['v'])) {
                $fileVersion = $jsonData['v'];
            }
        }
        // 3. fetch new copy of server_list.json
        $serverListResponse = $httpClient->send(new HttpClientRequest('GET', $serverListUrl));
        $serverListSignatureResponse = $httpClient->send(new HttpClientRequest('GET', $serverListSignatureUrl));

        // 4. verify signature
        if (!$signatureVerifier->verifyDetached($serverListResponse->body(), $serverListSignatureResponse->body())) {
            // unable to verify signature :-(
            exit(-1);
        }

        // 5. if version is newer than what we have...
        $jsonData = Json::decode($serverListResponse->body());
        if (!\array_key_exists('v', $jsonData) || !\is_int($jsonData['v'])) {
            // broken JSON file :-(
            exit(-2);
        }

        if ($jsonData['v'] <= $fileVersion) {
            // not newer than what we have, nothing to be done :-)
            return;
        }

        // 6. ...write to disk
        FileIO::write($serverListFile, $serverListResponse->body());
    }

    /**
     * Get Public Key based on Key ID.
     *
     * @return ?string the Public Key, or null if Public Key is not available
     */
    public function fromKeyId(string $keyId): ?string
    {
        return $this->extractPublicKey($keyId);
    }

    public function extractPublicKey(string $keyId): ?string
    {
        [$publicKey] = $this->extractPublicKeyBaseUrl($keyId);

        return $publicKey;
    }

    public function extractBaseUrl(string $keyId): ?string
    {
        [, $baseUrl] = $this->extractPublicKeyBaseUrl($keyId);

        return $baseUrl;
    }

    /**
     * Extract the public key together with the "base_url" the public key
     * belongs to from the "server_list.json" based on a key ID.
     *
     * Returns null if there is no entry with this key ID or when the file is
     * missing or corrupt.
     *
     * @return ?array{0:string,1:string}
     */
    private function extractPublicKeyBaseUrl(string $keyId): ?array
    {
        $serverListUrl = $this->apiConfig->guestAccessServerListUrl();
        $serverListFile = $this->dataDir.'/'.Base64UrlSafe::encodeUnpadded($serverListUrl);
        if (!FileIO::exists($serverListFile)) {
            return null;
        }
        $jsonData = Json::decode(FileIO::read($serverListFile));
        if (!\array_key_exists('server_list', $jsonData) || !\is_array($jsonData['server_list'])) {
            return null;
        }
        foreach ($jsonData['server_list'] as $serverInfo) {
            if (!\array_key_exists('server_type', $serverInfo)) {
                continue;
            }
            if ('secure_internet' !== $serverInfo['server_type']) {
                continue;
            }
            if (!\array_key_exists('public_key_list', $serverInfo) || !\is_array($serverInfo['public_key_list'])) {
                continue;
            }
            foreach ($serverInfo['public_key_list'] as $publicKey) {
                if (!\is_string($publicKey)) {
                    continue;
                }
                if (0 !== strpos($publicKey, 'k7.pub.')) {
                    continue;
                }
                if ($keyId !== substr($publicKey, 7, 16)) {
                    continue;
                }
                if (!\array_key_exists('base_url', $serverInfo) || !\is_string($serverInfo['base_url'])) {
                    continue;
                }

                return [
                    $publicKey,
                    $serverInfo['base_url'],
                ];
            }
        }

        return null;
    }
}
