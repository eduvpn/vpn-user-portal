<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Crypto\Minisign;

use SplFileObject;
use Vpn\Portal\Base64;
use Vpn\Portal\Binary;
use Vpn\Portal\Crypto\Minisign\Exception\MinisignException;

class Signature
{
    private const SUPPORTED_SIGNATURE_ALGOS = ['Ed', 'ED'];
    private const KEY_ID_LENGTH = 8;
    private const SIGNATURE_ALGO_LENGTH = 2;
    private const SIGNATURE_LENGTH = 64;

    private string $signatureAlgo;
    private string $keyId;
    private string $rawSignature;

    public function __construct(string $encodedSignature)
    {
        // 00000000  45 64 d5 82 09 60 68 5b  3d 2e 8a 78 78 4d f0 87  |Ed?..`h[=..xxM?.|
        // 00000010  06 ff 5e 9e c8 5c be 25  65 9d b4 1a 6d b4 c9 45  |.?^.?\?%e.?.m??E|
        // 00000020  44 ff ce 70 4e fd d3 24  91 91 fc 8a 72 f1 16 24  |D??pN??$..?.r?.$|
        // 00000030  22 10 0b 86 2c 70 b7 88  1b 24 bf d8 76 4f 67 7a  |"...,p?..$??vOgz|
        // 00000040  d5 6a 29 92 94 59 1f fb  24 02                    |?j)..Y.?$.|
        // 0000004a
        $rawSignature = Base64::decode($encodedSignature);
        if (self::SIGNATURE_ALGO_LENGTH + self::KEY_ID_LENGTH + self::SIGNATURE_LENGTH !== Binary::safeStrlen($rawSignature)) {
            throw new MinisignException('invalid signature length');
        }
        $signatureAlgo = Binary::safeSubstr($rawSignature, 0, self::SIGNATURE_ALGO_LENGTH);
        if (!\in_array($signatureAlgo, self::SUPPORTED_SIGNATURE_ALGOS, true)) {
            throw new MinisignException('invalid signature algorithm');
        }

        $this->signatureAlgo = $signatureAlgo;
        $this->keyId = Binary::safeSubstr($rawSignature, self::SIGNATURE_ALGO_LENGTH, self::KEY_ID_LENGTH);
        $this->rawSignature = Binary::safeSubstr($rawSignature, self::SIGNATURE_ALGO_LENGTH + self::KEY_ID_LENGTH);
    }

    public static function fromString(string $signatureFileContent): self
    {
        // untrusted comment: signature from minisign secret key
        // RWTVgglgaFs9Lop4eE3whwb/Xp7IXL4lZZ20Gm20yUVE/85wTv3TJJGR/Ipy8RYkIhALhixwt4gbJL/Ydk9netVqKZKUWR/7JAI=
        // trusted comment: timestamp:1663940532	file:minisign.pub
        // 4dl3Y9BBDWfq93HpbJ0zsGZpCWmjjivGKqEgJqZeYqAPO8ZNharIJP8/05TCJgNGMTp+bdVKxmBYkmInDwqsAg==
        $signatureFileLines = explode("\n", $signatureFileContent);
        if (!\array_key_exists(1, $signatureFileLines)) {
            throw new MinisignException('invalid signature file');
        }

        return new self(trim($signatureFileLines[1]));
    }

    public static function fromFile(string $fileName): self
    {
        // untrusted comment: signature from minisign secret key
        // RWTVgglgaFs9Lop4eE3whwb/Xp7IXL4lZZ20Gm20yUVE/85wTv3TJJGR/Ipy8RYkIhALhixwt4gbJL/Ydk9netVqKZKUWR/7JAI=
        // trusted comment: timestamp:1663940532	file:minisign.pub
        // 4dl3Y9BBDWfq93HpbJ0zsGZpCWmjjivGKqEgJqZeYqAPO8ZNharIJP8/05TCJgNGMTp+bdVKxmBYkmInDwqsAg==
        $f = new SplFileObject($fileName);
        // we want to get the *second* line from the file
        $f->seek(1);

        $secondLine = $f->current();
        if (!\is_string($secondLine)) {
            // couldn't get the second line
            throw new MinisignException('invalid signature file');
        }

        // trim the line as to not trip up the Base64 decoder
        return new self(trim($secondLine));
    }

    public function signatureAlgo(): string
    {
        return $this->signatureAlgo;
    }

    public function raw(): string
    {
        return $this->rawSignature;
    }

    public function keyId(): string
    {
        return $this->keyId;
    }
}
