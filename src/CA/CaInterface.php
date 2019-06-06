<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTimeInterface;

interface CaInterface
{
    public function caCert(): string;

    public function serverCert(string $commonName): CertInfo;

    public function clientCert(string $commonName, DateTimeInterface $expiresAt): CertInfo;
}
