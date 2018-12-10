<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use ParagonIE\ConstantTime\Base64;
use RuntimeException;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Portal\HttpClient\HttpClientInterface;

class ForeignKeyListFetcher
{
    /** @var string */
    private $filePath;

    /**
     * @param string $filePath
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @param HttpClient\HttpClientInterface $httpClient
     * @param string                         $discoveryUrl
     * @param string                         $encodedPublicKey
     *
     * @return void
     */
    public function update(HttpClientInterface $httpClient, $discoveryUrl, $encodedPublicKey)
    {
        $publicKey = Base64::decode($encodedPublicKey);
        $discoverySignatureUrl = sprintf('%s.sig', $discoveryUrl);

        $discoveryResponse = $httpClient->get($discoveryUrl);
        $discoverySignatureResponse = $httpClient->get($discoverySignatureUrl);

        $discoverySignature = Base64::decode($discoverySignatureResponse->getBody());
        $discoveryBody = $discoveryResponse->getBody();

        if (!sodium_crypto_sign_verify_detached($discoverySignature, $discoveryBody, $publicKey)) {
            throw new RuntimeException('unable to verify signature');
        }

        $seq = self::getSequence($this->filePath);

        // check whether "seq" was lower than current version
        $discoveryData = $discoveryResponse->json();
        if ($discoveryData['seq'] < $seq) {
            throw new RuntimeException('rollback, this is really unexpected!');
        }

        // write file when "seq" was incremented
        if ($discoveryData['seq'] > $seq) {
            FileIO::writeFile($this->filePath, $discoveryBody);
        }

        // do nothing when "seq" is the same
    }

    /**
     * @return array
     */
    public function extract()
    {
        if (false === FileIO::exists($this->filePath)) {
            return [];
        }
        $jsonData = FileIO::readJsonFile($this->filePath);
        $entryList = [];
        foreach ($jsonData['instances'] as $instance) {
            // convert base_uri to FQDN
            $baseUri = $instance['base_uri'];
            $hostName = parse_url($baseUri, PHP_URL_HOST);
            if (!\is_string($hostName)) {
                throw new RuntimeException('unable to extract host name from base_uri');
            }
            $entryList[$hostName] = Base64::decode($instance['public_key']);
        }

        return $entryList;
    }

    /**
     * @param string $filePath
     *
     * @return int
     */
    private static function getSequence($filePath)
    {
        if (false === FileIO::exists($filePath)) {
            return 0;
        }
        $jsonData = FileIO::readJsonFile($filePath);
        if (!array_key_exists('seq', $jsonData)) {
            throw new RuntimeException('unable to extract "seq" from file');
        }

        return (int) $jsonData['seq'];
    }
}
