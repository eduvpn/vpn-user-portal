<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\Jwt\Keys\EdDSA\PublicKey;
use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\OAuth\PublicSigner;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

class ForeignKeyListFetcher
{
    /** @var string */
    private $dataDir;

    /**
     * @param string $dataDir
     */
    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @param HttpClient\HttpClientInterface $httpClient
     * @param array                          $remoteAccessList
     *
     * @return void
     */
    public function update(HttpClientInterface $httpClient, array $remoteAccessList)
    {
        $agreggateDiscoveryData = [];
        foreach ($remoteAccessList as $sourceName => $sourceInfo) {
            $discoveryUrl = $sourceInfo['discovery_url'];
            $encodedPublicKey = $sourceInfo['public_key'];

            $discoveryResponse = $httpClient->get($discoveryUrl);
            $discoverySignatureResponse = $httpClient->get(sprintf('%s.sig', $discoveryUrl));

            $discoveryBody = $discoveryResponse->getBody();
            $discoverySignatureBody = Base64::decode($discoverySignatureResponse->getBody());

            $publicKey = Base64::decode($encodedPublicKey);
            if (!sodium_crypto_sign_verify_detached($discoverySignatureBody, $discoveryBody, $publicKey)) {
                throw new RuntimeException('unable to verify signature');
            }

            // obtain the "seq" of the current stored discovery file
            $currentSeq = $this->getCurrentSequence($sourceName);

            $discoveryData = $discoveryResponse->json();
            if ($discoveryData['seq'] < $currentSeq) {
                throw new RuntimeException('existing "seq" is lower than "seq" of update!');
            }

            // write file when "seq" was incremented
            if ($discoveryData['seq'] > $currentSeq) {
                $discoveryFile = sprintf('%s/%s.json', $this->dataDir, $sourceName);
                FileIO::writeFile($discoveryFile, $discoveryBody);
            }
            // do nothing when "seq" is the same as before
            $agreggateDiscoveryData[$sourceName] = $discoveryData['instances'];
        }

        // regenerate the mapping...
        $mappingFile = sprintf('%s/key_instance_mapping.json', $this->dataDir);
        FileIO::writeFile($mappingFile, Json::encode(self::generateMapping($agreggateDiscoveryData)));
    }

    /**
     * @return array
     */
    public function extract()
    {
        $mappingFile = sprintf('%s/key_instance_mapping.json', $this->dataDir);
        if (false === FileIO::exists($mappingFile)) {
            return [];
        }

        return FileIO::readJsonFile($mappingFile);
    }

    /**
     * @param string $remoteSourceName
     *
     * @return int
     */
    private function getCurrentSequence($remoteSourceName)
    {
        $discoveryFile = sprintf('%s/%s.json', $this->dataDir, $remoteSourceName);
        if (false === FileIO::exists($discoveryFile)) {
            return 0;
        }
        $jsonData = FileIO::readJsonFile($discoveryFile);

        return (int) $jsonData['seq'];
    }

    /**
     * @param array $discoveryData
     *
     * @return array
     */
    private static function generateMapping(array $discoveryData)
    {
        $mappingData = [];
        foreach ($discoveryData as $sourceName => $sourceInfo) {
            foreach ($sourceInfo as $instanceInfo) {
                foreach ($instanceInfo['public_key_list'] as $publicKeyStr) {
                    $publicKey = new PublicKey(Base64UrlSafe::decode($publicKeyStr));
                    $mappingData[PublicSigner::calculateKeyId($publicKey)] = [
                        'public_key' => $publicKey->encode(),
                        'base_uri' => $instanceInfo['base_uri'],
                        'source_name' => $sourceName,
                    ];
                }
            }
        }

        return $mappingData;
    }
}
