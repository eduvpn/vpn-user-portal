<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTime;
use LC\Common\FileIO;
use LC\Portal\CA\Exception\CaException;
use RuntimeException;

class VpnCa implements CaInterface
{
    /** @var string */
    private $caDir;

    /** @var string */
    private $caKeyType;

    /** @var string */
    private $vpnCaPath;

    /**
     * @param string $caDir
     * @param string $caKeyType
     * @param string $vpnCaPath
     */
    public function __construct($caDir, $caKeyType, $vpnCaPath)
    {
        $this->caDir = $caDir;
        $this->caKeyType = $caKeyType;
        $this->vpnCaPath = $vpnCaPath;
        $this->init();
    }

    /**
     * Get the CA root certificate.
     *
     * @return string the CA certificate in PEM format
     */
    public function caCert()
    {
        $certFile = sprintf('%s/ca.crt', $this->caDir);

        return $this->readCertificate($certFile);
    }

    /**
     * Generate a certificate for the VPN server.
     *
     * @param string $commonName
     *
     * @return array the certificate, key in array with keys
     *               'cert', 'key', 'valid_from' and 'valid_to'
     */
    public function serverCert($commonName)
    {
        $this->execVpnCa(sprintf('-server -name "%s" -not-after CA', $commonName));

        return $this->certInfo($commonName);
    }

    /**
     * Generate a certificate for a VPN client.
     *
     * @param string $commonName
     *
     * @return array the certificate and key in array with keys 'cert', 'key',
     *               'valid_from' and 'valid_to'
     */
    public function clientCert($commonName, DateTime $expiresAt)
    {
        // prevent expiresAt to be in the past
        $dateTime = new DateTime();
        if ($dateTime->getTimestamp() >= $expiresAt->getTimestamp()) {
            throw new CaException(sprintf('can not issue certificates that expire in the past (%s)', $expiresAt->format(DateTime::ATOM)));
        }

        $this->execVpnCa(sprintf('-client -name "%s" -not-after %s', $commonName, $expiresAt->format(DateTime::ATOM)));

        return $this->certInfo($commonName);
    }

    /**
     * @return bool
     */
    private function isInitialized()
    {
        $hasKey = FileIO::exists(sprintf('%s/ca.key', $this->caDir));
        $hasCert = FileIO::exists(sprintf('%s/ca.crt', $this->caDir));

        return $hasKey && $hasCert;
    }

    /**
     * @return void
     */
    private function init()
    {
        if ($this->isInitialized()) {
            return;
        }

        if (!FileIO::exists($this->caDir)) {
            // we do not have the CA dir, create it
            FileIO::createDir($this->caDir, 0700);
        }

        // intitialize new CA
        $this->execVpnCa('-init-ca -name "VPN CA"');
    }

    /**
     * @param string $commonName
     *
     * @return array<string,string>
     */
    private function certInfo($commonName)
    {
        $certKeyInfo = $this->certKeyInfo(
            sprintf('%s/%s.crt', $this->caDir, $commonName),
            sprintf('%s/%s.key', $this->caDir, $commonName)
        );

        // delete the crt and key from disk as we no longer need them
        self::delete(sprintf('%s/%s.crt', $this->caDir, $commonName));
        self::delete(sprintf('%s/%s.key', $this->caDir, $commonName));

        return $certKeyInfo;
    }

    /**
     * @param string $certFile
     * @param string $keyFile
     *
     * @return array<string,string>
     */
    private function certKeyInfo($certFile, $keyFile)
    {
        $certData = $this->readCertificate($certFile);
        $keyData = $this->readKey($keyFile);
        $parsedCert = openssl_x509_parse($certData);

        return [
            'certificate' => $certData,
            'private_key' => $keyData,
            'valid_from' => $parsedCert['validFrom_time_t'],
            'valid_to' => $parsedCert['validTo_time_t'],
        ];
    }

    /**
     * @param string $certFile
     *
     * @return string
     */
    private function readCertificate($certFile)
    {
        // strip whitespace before and after actual certificate
        return trim(FileIO::readFile($certFile));
    }

    /**
     * @param string $keyFile
     *
     * @return string
     */
    private function readKey($keyFile)
    {
        // strip whitespace before and after actual key
        return trim(FileIO::readFile($keyFile));
    }

    /**
     * @param string $cmdArgs
     *
     * @return void
     */
    private function execVpnCa($cmdArgs)
    {
        self::exec(sprintf('CA_DIR=%s CA_KEY_TYPE=%s %s %s', $this->caDir, $this->caKeyType, $this->vpnCaPath, $cmdArgs));
    }

    /**
     * @param string $execCmd
     *
     * @return void
     */
    private static function exec($execCmd)
    {
        exec(
            sprintf('%s 2>&1', $execCmd),
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(sprintf('command "%s" did not complete successfully: "%s"', $execCmd, implode(\PHP_EOL, $commandOutput)));
        }
    }

    /**
     * @param string $fileName
     *
     * @return void
     */
    private static function delete($fileName)
    {
        if (false === @unlink($fileName)) {
            throw new RuntimeException(sprintf('unable to delete "%s"', $fileName));
        }
    }
}
