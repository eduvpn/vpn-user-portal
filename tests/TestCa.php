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
use Vpn\Portal\CA\CaInterface;

class TestCa implements CaInterface
{
    /**
     * Generate a certificate for the VPN server.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function serverCert(string $commonName): array
    {
        return [
            'cert' => sprintf('ServerCert for %s', $commonName),
            'key' => sprintf('ServerCert for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => 2345678901,
        ];
    }

    /**
     * Generate a certificate for a VPN client.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function clientCert(string $commonName, DateTimeImmutable $expiresAt)
    {
        return [
            'cert' => sprintf('ClientCert for %s', $commonName),
            'key' => sprintf('ClientKey for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => $expiresAt->getTimestamp(),
        ];
    }

    /**
     * Get the CA root certificate.
     */
    public function caCert(): string
    {
        return 'Ca';
    }
}
