<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTimeImmutable;

interface CaInterface
{
    /**
     * Get the CA root certificate.
     */
    public function caCert(): string;

    /**
     * Generate a certificate for the VPN server.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function serverCert(string $commonName): array;

    /**
     * Generate a certificate for a VPN client.
     *
     * @return array{cert:string,key:string,valid_from:int,valid_to:int}
     */
    public function clientCert(string $commonName, DateTimeImmutable $expiresAt): array;
}
