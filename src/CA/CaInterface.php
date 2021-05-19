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
    public function caCert(): CaInfo;

    /**
     * Generate a certificate for the VPN server.
     */
    public function serverCert(string $commonName, string $profileId): CertInfo;

    /**
     * Generate a certificate for a VPN client.
     */
    public function clientCert(string $commonName, string $profileId, DateTimeImmutable $expiresAt): CertInfo;
}
