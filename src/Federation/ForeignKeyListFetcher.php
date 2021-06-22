<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

use Exception;
use LC\Portal\Dt;
use LC\Portal\FileIO;
use LC\Portal\HttpClient\HttpClientInterface;
use LC\Portal\Json;

class ForeignKeyListFetcher
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @param array<string> $trustedPublicKeyList
     */
    public function update(HttpClientInterface $httpClient, string $serverListUrl, array $trustedPublicKeyList): void
    {
        $requestHeaders = [];
        if (false !== $filemTime = @filemtime($this->dataDir.'/server_list.json')) {
            $fileModifiedDateTimeImmutable = Dt::get('@'.$filemTime);
            $requestHeaders[] = 'If-Modified-Since: '.$fileModifiedDateTimeImmutable->format('D, d M Y H:i:s \G\M\T');
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
        if (null !== $lastModified = $serverListResponse->getHeader('Last-Modified')) {
            // use Last-Modified header to set the file's modified time, if
            // available from server to be used on future requests as the
            // "If-Modified-Since" header value
            $lastModifiedDt = Dt::get($lastModified);
            touch($this->dataDir.'/server_list.json', $lastModifiedDt->getTimestamp());
        }
    }

    private function getCurrentVersion(): int
    {
        if (!FileIO::exists($this->dataDir.'/server_list.json')) {
            return 0;
        }
        $serverListData = Json::decode(FileIO::readFile($this->dataDir.'/server_list.json'));

        return $serverListData['v'];
    }
}
