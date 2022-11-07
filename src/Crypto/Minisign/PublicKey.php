<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto\Minisign;

use SplFileObject;
use Vpn\Portal\Base64;
use Vpn\Portal\Binary;
use Vpn\Portal\Crypto\Minisign\Exception\MinisignException;

/**
 * @see https://jedisct1.github.io/minisign/#public-key-format
 */
class PublicKey
{
    private const PUBLIC_KEY_LENGTH = 32;
    private const KEY_ID_LENGTH = 8;
    private const KEY_ALGO_LENGTH = 2;

    private string $keyId;
    private string $publicKey;

    public function __construct(string $encodedPublicKey)
    {
        // 00000000  45 64 d5 82 09 60 68 5b  3d 2e cf 20 54 68 ed 0f  |Ed?..`h[=.? Th?.|
        // 00000010  7b 4f 48 0b 4b 42 63 92  68 c9 e6 c4 9f 63 d5 bd  |{OH.KBc.h???.cս|
        // 00000020  c1 b1 fc 63 45 86 9e e5  bf d6                    |???cE..??|
        // 0000002a
        $publicKey = Base64::decode($encodedPublicKey);
        if (self::KEY_ALGO_LENGTH + self::KEY_ID_LENGTH + self::PUBLIC_KEY_LENGTH !== strlen($publicKey)) {
            throw new MinisignException('public key has invalid length');
        }
        if ('Ed' !== substr($publicKey, 0, self::KEY_ALGO_LENGTH)) {
            throw new MinisignException('public key has invalid algorithm');
        }
        $this->keyId = substr($publicKey, self::KEY_ALGO_LENGTH, self::KEY_ID_LENGTH);
        $this->publicKey = substr($publicKey, self::KEY_ALGO_LENGTH + self::KEY_ID_LENGTH);
    }

    public static function fromFile(string $fileName): self
    {
        // untrusted comment: minisign public key 2E3D5B68600982D5
        // RWTVgglgaFs9Ls8gVGjtD3tPSAtLQmOSaMnmxJ9j1b3BsfxjRYae5b/W
        $f = new SplFileObject($fileName);
        // we want to get the *second* line from the file
        $f->seek(1);

        $secondLine = $f->current();
        if (!\is_string($secondLine)) {
            // couldn't get the second line
            throw new MinisignException('invalid public key file');
        }

        // trim the line as to not trip up the Base64 decoder
        return new self(trim($secondLine));
    }

    public function raw(): string
    {
        return $this->publicKey;
    }

    public function keyId(): string
    {
        return $this->keyId;
    }
}
