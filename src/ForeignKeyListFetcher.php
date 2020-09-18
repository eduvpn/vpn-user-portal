<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\Jwt\Keys\EdDSA\PublicKey;
use LC\Common\FileIO;
use LC\Common\Json;
use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\OAuth\PublicSigner;
use ParagonIE\ConstantTime\Base64UrlSafe;

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
     * @param string                         $serverListUrl
     * @param array<string>                  $trustedPublicKeyList
     *
     * @return void
     */
    public function update(HttpClientInterface $httpClient, $serverListUrl, array $trustedPublicKeyList)
    {
        // XXX implement last modified stuff
        $serverListResponse = $httpClient->get($serverListUrl);
        $serverListSigResponse = $httpClient->get($serverListUrl.'.minisig');

        if (200 !== $serverListResponse->getStatusCode()) {
            // unable to fetch server_list, or not modified
            return;
        }
        if (200 !== $serverListSigResponse->getStatusCode()) {
            // unable to fetch server_list signature, or not modified
            return;
        }

        if (false === Minisign::verify($serverListResponse->getBody(), $serverListSigResponse->getBody(), $trustedPublicKeyList)) {
            // signature not valid
            return;
        }

        $currentVersion = $this->getVersion();
        $serverListData = Json::decode($serverListResponse->getBody());
        if ($serverListData['v'] <= $currentVersion) {
            // not newer
            return;
        }

        FileIO::writeFile($this->dataDir.'/server_list.json', $serverListResponse->getBody());
        FileIO::writeFile(
            $this->dataDir.'/key_instance_mapping.json',
            Json::encode(
                self::generateMapping($serverListData)
            )
        );
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
     * @return int
     */
    private function getVersion()
    {
        if (!FileIO::exists($this->dataDir.'/server_list.json')) {
            return 0;
        }
        $serverListData = FileIO::readJsonFile($this->dataDir.'/server_list.json');

        return $serverListData['v'];
    }

    /**
     * @return array
     */
    private static function generateMapping(array $serverListData)
    {
        $mappingData = [];
        foreach ($serverListData['server_list'] as $serverEntry) {
            if ('secure_internet' !== $serverEntry['server_type']) {
                continue;
            }
            foreach ($serverEntry['public_key_list'] as $publicKeyStr) {
                $publicKey = new PublicKey(Base64UrlSafe::decode($publicKeyStr));
                $mappingData[PublicSigner::calculateKeyId($publicKey)] = [
                    'public_key' => $publicKey->encode(),
                    'base_uri' => $serverEntry['base_url'],
                ];
            }
        }

        return $mappingData;
    }
}
