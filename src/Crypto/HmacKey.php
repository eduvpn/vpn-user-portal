<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto;

use Vpn\Portal\Base64UrlSafe;
use Vpn\Portal\Crypto\Exception\CryptoException;

class HmacKey
{
    private const KEY_LEN = 32; // length of sha256 hash output
    private string $hmacKey;

    private function __construct(string $hmacKey)
    {
        if (self::KEY_LEN !== strlen($hmacKey)) {
            throw new CryptoException('invalid key length');
        }

        $this->hmacKey = $hmacKey;
    }

    public static function generate(): self
    {
        return new self(random_bytes(self::KEY_LEN));
    }

    public function encode(): string
    {
        return Base64UrlSafe::encodeUnpadded($this->hmacKey);
    }

    public static function load(string $hmacKey): self
    {
        return new self(Base64UrlSafe::decode($hmacKey));
    }

    public function raw(): string
    {
        return $this->hmacKey;
    }
}
