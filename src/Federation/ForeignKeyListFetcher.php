<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

use DateTime;
use Exception;
use fkooman\Jwt\Keys\EdDSA\PublicKey;
use LC\Common\FileIO;
use LC\Common\HttpClient\HttpClientInterface;
use LC\Common\Json;
use LC\Portal\OAuth\PublicSigner;

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
     * @param string        $serverListUrl
     * @param array<string> $trustedPublicKeyList
     */
    public function update(HttpClientInterface $httpClient, $serverListUrl, array $trustedPublicKeyList): void
    {
        $requestHeaders = [];
        if (false !== $filemTime = @filemtime($this->dataDir.'/server_list.json')) {
            $fileModifiedDateTime = new DateTime('@'.$filemTime);
            $requestHeaders[] = 'If-Modified-Since: '.$fileModifiedDateTime->format('D, d M Y H:i:s \G\M\T');
        }
        $serverListResponse = $httpClient->get($serverListUrl, [], $requestHeaders);
        if (200 !== $serverListResponse->getCode()) {
            // unable to fetch server_list, or not modified
            return;
        }
        $serverListSigResponse = $httpClient->get($serverListUrl.'.minisig', [], []);
        if (200 !== $serverListSigResponse->getCode()) {
            // unable to fetch server_list signature, or not modified
            return;
        }

        if (false === Minisign::verify($serverListResponse->getBody(), $serverListSigResponse->getBody(), $trustedPublicKeyList)) {
            // signature not valid
            throw new Exception('unable to verify signature');
        }

        $currentVersion = $this->getCurrentVersion();
        $serverListData = Json::decode($serverListResponse->getBody());
        if ($serverListData['v'] === $currentVersion) {
            // same version, do not update
            return;
        }
        if ($serverListData['v'] < $currentVersion) {
            // new file is older, that should never happen
            throw new Exception(sprintf('rollback to older version of file not allowed, we have "%d", we got "%d"', $currentVersion, $serverListData['v']));
        }

        FileIO::writeFile($this->dataDir.'/server_list.json', $serverListResponse->getBody());
        FileIO::writeFile(
            $this->dataDir.'/key_instance_mapping.json',
            Json::encode(
                self::generateMapping($serverListData)
            )
        );

        if (null !== $lastModified = $serverListResponse->getHeader('Last-Modified')) {
            // use Last-Modified header to set the file's modified time, if
            // available from server to be used on future requests as the
            // "If-Modified-Since" header value
            $lastModifiedDateTime = new DateTime($lastModified);
            touch($this->dataDir.'/server_list.json', $lastModifiedDateTime->getTimestamp());
        }
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
    private function getCurrentVersion()
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
                // XXX why first decode and then encode?
                $publicKey = new PublicKey(
                    sodium_base642bin($publicKeyStr, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)
                );
                $mappingData[PublicSigner::calculateKeyId($publicKey)] = [
                    'public_key' => $publicKey->encode(),
                    'base_uri' => $serverEntry['base_url'],
                ];
            }
        }

        return $mappingData;
    }
}
