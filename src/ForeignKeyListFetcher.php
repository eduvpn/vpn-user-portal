<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\Http\HttpClientInterface;
use fkooman\OAuth\Client\Http\Request;
use ParagonIE\ConstantTime\Base64;
use RuntimeException;
use SURFnet\VPN\Common\Json;

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
     * @param HttpClientInterface $httpClient
     * @param string              $discoveryUrl
     * @param string              $encodedPublicKey
     *
     * @return void
     */
    public function update(HttpClientInterface $httpClient, $discoveryUrl, $encodedPublicKey)
    {
        $publicKey = Base64::decode($encodedPublicKey);
        $discoverySignatureUrl = sprintf('%s.sig', $discoveryUrl);

        $discoveryResponse = $this->httpGet($httpClient, $discoveryUrl);
        $discoverySignatureResponse = $this->httpGet($httpClient, $discoverySignatureUrl);

        $discoverySignature = Base64::decode($discoverySignatureResponse->getBody());
        $discoveryBody = $discoveryResponse->getBody();

        if (!sodium_crypto_sign_verify_detached($discoverySignature, $discoveryBody, $publicKey)) {
            throw new RuntimeException('unable to verify signature');
        }

        $seq = self::getSequence($this->filePath);

        $discoveryData = $discoveryResponse->json();
        if ($discoveryData['seq'] < $seq) {
            throw new RuntimeException('rollback, this is really unexpected!');
        }

        // all fine, write file
        if (false === file_put_contents($this->filePath, $discoveryBody)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $this->filePath));
        }
    }

    /**
     * @return array
     */
    public function extract()
    {
        if (false === $fileContent = file_get_contents($this->filePath)) {
            return [];
        }

        $jsonData = Json::decode($fileContent);

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
     * @param HttpClientInterface $httpClient
     * @param string              $requestUrl
     *
     * @return \fkooman\OAuth\Client\Http\Response
     */
    private function httpGet(HttpClientInterface $httpClient, $requestUrl)
    {
        $httpResponse = $httpClient->send(Request::get($requestUrl));
        if (!$httpResponse->isOkay()) {
            throw new RuntimeException(sprintf('unable to fetch "%s"', $requestUrl));
        }

        return $httpResponse;
    }

    /**
     * @param string $filePath
     *
     * @return int
     */
    private static function getSequence($filePath)
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        if (false === $fileContent = file_get_contents($filePath)) {
            return 0;
        }

        $jsonData = Json::decode($fileContent);

        return (int) $jsonData['seq'];
    }
}
