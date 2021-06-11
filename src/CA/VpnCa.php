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
use LC\Portal\Dt;
use LC\Portal\FileIO;
use RuntimeException;

class VpnCa implements CaInterface
{
    protected DateTimeImmutable $dateTime;
    private string $caDir;
    private string $caKeyType;
    private string $vpnCaPath;
    private DateInterval $caExpiry;

    public function __construct(string $caDir, string $caKeyType, string $vpnCaPath, DateInterval $caExpiry)
    {
        $this->caDir = $caDir;
        $this->caKeyType = $caKeyType;
        $this->vpnCaPath = $vpnCaPath;
        $this->caExpiry = $caExpiry;
        $this->dateTime = Dt::get();
        $this->init();
    }

    public function caCert(): CaInfo
    {
        $pemCert = $this->readFile('ca.crt');
        $parsedPem = openssl_x509_parse($pemCert);

        return new CaInfo(
            $pemCert,
            (int) $parsedPem['validFrom_time_t'],
            (int) $parsedPem['validTo_time_t'],
        );
    }

    /**
     * Generate a certificate for the VPN server.
     */
    public function serverCert(string $commonName, string $profileId): CertInfo
    {
        $this->execVpnCa(sprintf('-server -name "%s" -ou "%s" -not-after CA', $commonName, $profileId));

        return $this->certInfo($commonName);
    }

    /**
     * Generate a certificate for a VPN client.
     */
    public function clientCert(string $commonName, string $profileId, DateTimeImmutable $expiresAt): CertInfo
    {
        // prevent expiresAt to be in the past
        if ($this->dateTime->getTimestamp() >= $expiresAt->getTimestamp()) {
            throw new CaException(sprintf('can not issue certificates that expire in the past (%s)', $expiresAt->format(DateTimeImmutable::ATOM)));
        }

        $this->execVpnCa(sprintf('-client -name "%s" -ou "%s" -not-after %s', $commonName, $profileId, $expiresAt->format(DateTimeImmutable::ATOM)));

        return $this->certInfo($commonName);
    }

    private function isInitialized(): bool
    {
        return $this->hasFile('ca.key') && $this->hasFile('ca.crt');
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
                $this->dateTime->add($this->caExpiry)->format(DateTimeImmutable::ATOM)
            )
        );
    }

    private function certInfo(string $commonName): CertInfo
    {
        $certKeyInfo = $this->certKeyInfo($commonName.'.crt', $commonName.'.key');

        // delete the crt and key from disk as we no longer need them
        $this->deleteFile($commonName.'.crt');
        $this->deleteFile($commonName.'.key');

        return $certKeyInfo;
    }

    private function certKeyInfo(string $certFile, string $keyFile): CertInfo
    {
        $pemCert = $this->readFile($certFile);
        $parsedPem = openssl_x509_parse($pemCert);

        return new CertInfo(
            $pemCert,
            $this->readFile($keyFile),
            (int) $parsedPem['validFrom_time_t'],
            (int) $parsedPem['validTo_time_t'],
        );
    }

    private function readFile(string $fileName): string
    {
        return trim(FileIO::readFile($this->caDir.'/'.$fileName));
    }

    private function hasFile(string $fileName): bool
    {
        return FileIO::exists($this->caDir.'/'.$fileName);
    }

    private function deleteFile(string $fileName): void
    {
        if (false === @unlink($this->caDir.'/'.$fileName)) {
            throw new RuntimeException(sprintf('unable to delete "%s"', $this->caDir.'/'.$fileName));
        }
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
}
