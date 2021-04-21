<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateInterval;
use DateTimeImmutable;
use LC\Portal\CA\Exception\CaException;
use LC\Portal\FileIO;
use RuntimeException;

class VpnCa implements CaInterface
{
    protected DateTimeImmutable $dateTime;
    private string $caDir;
    private string $caKeyType;
    private string $vpnCaPath;

    public function __construct(string $caDir, string $caKeyType, string $vpnCaPath)
    {
        $this->caDir = $caDir;
        $this->caKeyType = $caKeyType;
        $this->vpnCaPath = $vpnCaPath;
        $this->dateTime = new DateTimeImmutable();
        $this->init();
    }

    /**
     * Get the CA root certificate.
     */
    public function caCert(): string
    {
        $certFile = sprintf('%s/ca.crt', $this->caDir);

        return $this->readCertificate($certFile);
    }

    public function caExpiresAt(): DateTimeImmutable
    {
        $certData = $this->readCertificate($this->caDir.'/ca.crt');
        $certInfo = openssl_x509_parse($certData);
        if (!\is_array($certInfo)) {
            throw new CaException('unable to parse CA certificate');
        }
        if (!\array_key_exists('validTo_time_t', $certInfo)) {
            throw new CaException('unable to extract "valid_to" from CA certificate');
        }
        $validTo = $certInfo['validTo_time_t'];
        if (!\is_int($validTo)) {
            throw new CaException('"validTo_time_t" not of type int');
        }

        return new DateTimeImmutable('@'.$validTo);
    }

    /**
     * Generate a certificate for the VPN server.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function serverCert(string $commonName): array
    {
        $this->execVpnCa(sprintf('-server -name "%s" -not-after CA', $commonName));

        return $this->certInfo($commonName);
    }

    /**
     * Generate a certificate for a VPN client.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function clientCert(string $commonName, DateTimeImmutable $expiresAt)
    {
        // prevent expiresAt to be in the past
        $dateTime = new DateTimeImmutable();
        if ($dateTime->getTimestamp() >= $expiresAt->getTimestamp()) {
            throw new CaException(sprintf('can not issue certificates that expire in the past (%s)', $expiresAt->format(DateTimeImmutable::ATOM)));
        }

        $this->execVpnCa(sprintf('-client -name "%s" -not-after %s', $commonName, $expiresAt->format(DateTimeImmutable::ATOM)));

        return $this->certInfo($commonName);
    }

    private function isInitialized(): bool
    {
        $hasKey = FileIO::exists(sprintf('%s/ca.key', $this->caDir));
        $hasCert = FileIO::exists(sprintf('%s/ca.crt', $this->caDir));

        return $hasKey && $hasCert;
    }

    private function init(): void
    {
        if ($this->isInitialized()) {
            return;
        }

        if (!FileIO::exists($this->caDir)) {
            // we do not have the CA dir, create it
            FileIO::createDir($this->caDir, 0700);
        }

        // intitialize new CA
        $this->execVpnCa(
            sprintf(
                '-init-ca -not-after %s -name "VPN CA"',
                $this->dateTime->add(new DateInterval('P10Y'))->format(DateTimeImmutable::ATOM)
            )
        );
    }

    /**
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    private function certInfo(string $commonName): array
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
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    private function certKeyInfo(string $certFile, string $keyFile): array
    {
        $certData = $this->readCertificate($certFile);
        $keyData = $this->readKey($keyFile);
        $parsedCert = openssl_x509_parse($certData);

        return [
            'cert' => $certData,
            'key' => $keyData,
            // XXX make sure the next two exist and are "int"!
            'valid_from' => (int) $parsedCert['validFrom_time_t'],
            'valid_to' => (int) $parsedCert['validTo_time_t'],
        ];
    }

    private function readCertificate(string $certFile): string
    {
        // strip whitespace before and after actual certificate
        return trim(FileIO::readFile($certFile));
    }

    private function readKey(string $keyFile): string
    {
        // strip whitespace before and after actual key
        return trim(FileIO::readFile($keyFile));
    }

    private function execVpnCa(string $cmdArgs): void
    {
        self::exec(sprintf('CA_DIR=%s CA_KEY_TYPE=%s %s %s', $this->caDir, $this->caKeyType, $this->vpnCaPath, $cmdArgs));
    }

    private static function exec(string $execCmd): void
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

    private static function delete(string $fileName): void
    {
        if (false === @unlink($fileName)) {
            throw new RuntimeException(sprintf('unable to delete "%s"', $fileName));
        }
    }
}
