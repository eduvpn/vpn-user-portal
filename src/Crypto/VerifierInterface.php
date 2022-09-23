<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto;

interface VerifierInterface
{
    /**
     * Verify a detached signature.
     */
    public function verifyDetached(string $plainText, string $signatureString): bool;
}
