<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTime;
use DateTimeInterface;
use LC\Portal\CA\Exception\CaException;
use LC\Portal\FileIO;
use RuntimeException;

class EasyRsaCa implements CaInterface
{
    /** @var string */
    private $easyRsaDir;

    /** @var string */
    private $easyRsaDataDir;

    /**
     * @param string $easyRsaDir
     * @param string $easyRsaDataDir
     */
    public function __construct($easyRsaDir, $easyRsaDataDir)
    {
        $this->easyRsaDir = $easyRsaDir;
        $this->easyRsaDataDir = $easyRsaDataDir;
    }

    /**
     * @return void
     */
    public function init()
    {
        FileIO::createDir($this->easyRsaDataDir, 0700);

        // only initialize when unitialized, prevent destroying existing CA
        if (false === FileIO::exists(sprintf('%s/vars', $this->easyRsaDataDir))) {
            $configData = [
                sprintf('set_var EASYRSA "%s"', $this->easyRsaDir),
                sprintf('set_var EASYRSA_PKI "%s/pki"', $this->easyRsaDataDir),
                'set_var EASYRSA_KEY_SIZE 3072',
                'set_var EASYRSA_CA_EXPIRE 1800',
                'set_var EASYRSA_REQ_CN	"VPN CA"',
                'set_var EASYRSA_BATCH "1"',
            ];

            FileIO::writeFile(
                sprintf('%s/vars', $this->easyRsaDataDir),
                implode(PHP_EOL, $configData).PHP_EOL,
                0600
            );

            $this->execEasyRsa(['init-pki']);
            $this->execEasyRsa(['build-ca', 'nopass']);
            $this->execEasyRsa(['update-db']);
        }
    }

    public function caCert(): string
    {
        $certFile = sprintf('%s/pki/ca.crt', $this->easyRsaDataDir);

        return $this->readCertificate($certFile);
    }

    public function serverCert(string $commonName): CertInfo
    {
        if ($this->hasCert($commonName)) {
            throw new CaException(sprintf('certificate with commonName "%s" already exists', $commonName));
        }
        $this->execEasyRsa(['--days=360', 'build-server-full', $commonName, 'nopass']);

        return $this->certInfo($commonName);
    }

    public function clientCert(string $commonName, DateTimeInterface $expiresAt): CertInfo
    {
        if ($this->hasCert($commonName)) {
            throw new CaException(sprintf('certificate with commonName "%s" already exists', $commonName));
        }

        // prevent expiresAt to be in the past
        $dateTime = new DateTime();
        if ($dateTime >= $expiresAt) {
            throw new CaException('can not issue certificates that expire in the past');
        }

        // the date format MUST be y (2 digit year), with Y (4 digit year) PHP
        // on CentOS 7 freaks out when parsing the certificate using
        // openssl_x509_parse...
        $this->execEasyRsa(
            [
                sprintf(
                    '--enddate=%s',
                    $expiresAt->format('ymdHis\Z')
                ),
                'build-client-full',
                $commonName,
                'nopass',
            ]
        );

        return $this->certInfo($commonName);
    }

    private function certInfo(string $commonName): CertInfo
    {
        $certData = $this->readCertificate(sprintf('%s/pki/issued/%s.crt', $this->easyRsaDataDir, $commonName));
        $keyData = $this->readKey(sprintf('%s/pki/private/%s.key', $this->easyRsaDataDir, $commonName));

        $parsedCert = openssl_x509_parse($certData);

        return new CertInfo(
            $certData,
            $keyData,
            new DateTime(sprintf('@%d', $parsedCert['validFrom_time_t'])),
            new DateTime(sprintf('@%d', $parsedCert['validTo_time_t']))
        );
    }

    /**
     * @param string $certFile
     *
     * @return string
     */
    private function readCertificate($certFile)
    {
        // strip junk before and after actual certificate
        $pattern = '/(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----)/msU';
        if (1 !== preg_match($pattern, FileIO::readFile($certFile), $matches)) {
            throw new CaException('unable to extract certificate');
        }

        return $matches[1];
    }

    /**
     * @param string $keyFile
     *
     * @return string
     */
    private function readKey($keyFile)
    {
        // strip whitespace before and after actual key
        return trim(
            FileIO::readFile($keyFile)
        );
    }

    /**
     * @param string $commonName
     *
     * @return bool
     */
    private function hasCert($commonName)
    {
        return FileIO::exists(
            sprintf(
                '%s/pki/issued/%s.crt',
                $this->easyRsaDataDir,
                $commonName
            )
        );
    }

    /**
     * @return void
     */
    private function execEasyRsa(array $argv)
    {
        $command = sprintf(
            '%s/easyrsa --vars=%s/vars %s >/dev/null 2>/dev/null',
            $this->easyRsaDir,
            $this->easyRsaDataDir,
            implode(' ', $argv)
        );

        exec(
            $command,
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(
                sprintf('command "%s" did not complete successfully: "%s"', $command, implode(PHP_EOL, $commandOutput))
            );
        }
    }
}
