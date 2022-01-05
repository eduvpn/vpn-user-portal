<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateTimeImmutable;
use Vpn\Portal\OpenVpn\CA\CaInfo;
use Vpn\Portal\OpenVpn\CA\CaInterface;
use Vpn\Portal\OpenVpn\CA\CertInfo;

class TestCa implements CaInterface
{
    /**
     * Get the CA root certificate.
     */
    public function caCert(): CaInfo
    {
        return new CaInfo('---CA---', 123456789, 234567890);
    }

    /**
     * Generate a certificate for the VPN server.
     */
    public function serverCert(string $serverName, string $profileId): CertInfo
    {
        return new CertInfo('---SERVER CERT---', '---SERVER KEY---');
    }

    /**
     * Generate a certificate for a VPN client.
     */
    public function clientCert(string $commonName, string $profileId, DateTimeImmutable $expiresAt): CertInfo
    {
        return new CertInfo('---CLIENT CERT---', '---CLIENT KEY---');
    }
}
