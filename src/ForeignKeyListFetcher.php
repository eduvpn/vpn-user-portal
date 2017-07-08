<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\Http\HttpClientInterface;
use fkooman\OAuth\Client\Http\Request;
use ParagonIE\ConstantTime\Base64;
use RuntimeException;

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
     */
    public function update(HttpClientInterface $httpClient, $discoveryUrl, $encodedPublicKey)
    {
        $publicKey = Base64::decode($encodedPublicKey);
        $discoverySignatureUrl = sprintf('%s.sig', $discoveryUrl);

        $discoveryResponse = $this->httpGet($httpClient, $discoveryUrl);
        $discoverySignatureResponse = $this->httpGet($httpClient, $discoverySignatureUrl);

        $discoverySignature = Base64::decode($discoverySignatureResponse->getBody());
        $discoveryBody = $discoveryResponse->getBody();

        if (!\Sodium\crypto_sign_verify_detached($discoverySignature, $discoveryBody, $publicKey)) {
            throw new RuntimeException('unable to verify signature');
        }

        // check if we already have a file from a previous run
        $seq = 0;
        if (false !== $fileContent = @file_get_contents($this->filePath)) {
            // extract the "seq" field to see if we got a newer version
            $jsonData = self::jsonDecode($fileContent);
            $seq = (int) $jsonData['seq'];
        }

        $discoveryData = $discoveryResponse->json();
        if ($discoveryData['seq'] < $seq) {
            throw new RuntimeException('rollback, this is really unexpected!');
        }

        // all fine, write file
        if (false === @file_put_contents($this->filePath, $discoveryBody)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $this->filePath));
        }
    }

    /**
     * @return array
     */
    public function extract()
    {
        if (false === $fileContent = @file_get_contents($this->filePath)) {
            return [];
        }

        $jsonData = self::jsonDecode($fileContent);

        $entryList = [];
        foreach ($jsonData['instances'] as $instance) {
            // convert base_uri to FQDN
            $baseUri = $instance['base_uri'];
            if (false === $hostName = parse_url($baseUri, PHP_URL_HOST)) {
                throw new RuntimeException('unable to extract host name from base_uri');
            }
            $entryList[$hostName] = $instance['public_key'];
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
     * @param string
     *
     * @return array
     */
    private static function jsonDecode($jsonText)
    {
        if (null === $jsonData = json_decode($jsonText, true)) {
            throw new RuntimeException('unable to decode JSON');
        }

        return $jsonData;
    }
}
