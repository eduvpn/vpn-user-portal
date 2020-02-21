<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use Exception;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Binary;

/**
 * Validate Signify/Minisign signatures.
 *
 * @see https://jedisct1.github.io/minisign/
 */
class Signify
{
    /** @var string */
    const SIGNIFY_ALGO_DESCRIPTION = 'Ed';

    /** @var int */
    const SIGNIFY_RANDOM_BYTES_LENGTH = 8;

    /** @var int */
    const ED_SIGNATURE_LENGTH = 64;

    /** @var int */
    const ED_PUBLIC_KEY_LENGTH = 32;

    /** @var string */
    private $keyId;

    /** @var string */
    private $publicKey;

    /**
     * @param string $signifyPublicKey
     */
    public function __construct($signifyPublicKey)
    {
        list($keyId, $publicKey) = self::verifyPublicKey($signifyPublicKey);
        $this->keyId = $keyId;
        $this->publicKey = $publicKey;
    }

    /**
     * @param string $messageText
     * @param string $messageSignature
     *
     * @return bool
     */
    public function verify($messageText, $messageSignature)
    {
        $signatureData = self::getSecondLine($messageSignature);
        $msgSig = Base64::decode($signatureData, true);
        // <signature_algorithm> || <key_id> || <signature>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes, matching the public key
        //    signature (PureEdDSA): ed25519(<file data>)
        if (Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH + self::ED_SIGNATURE_LENGTH !== Binary::safeStrlen($msgSig)) {
            throw new Exception('invalid signature (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== Binary::safeSubstr($msgSig, 0, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }
        if ($this->keyId !== Binary::safeSubstr($msgSig, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_RANDOM_BYTES_LENGTH)) {
            throw new Exception('signature does not match public key');
        }

        return sodium_crypto_sign_verify_detached(
            Binary::safeSubstr($msgSig, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH),
            $messageText,
            $this->publicKey
        );
    }

    /**
     * @param string $publicKeyText
     *
     * @return array{0:string,1:string}
     */
    private static function verifyPublicKey($publicKeyText)
    {
        $publicKeyData = self::getSecondLine($publicKeyText);
        $pubKey = Base64::decode($publicKeyData, true);
        // <signature_algorithm> || <key_id> || <public_key>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes
        //    public_key: Ed25519 public key
        if (Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH + self::ED_PUBLIC_KEY_LENGTH !== Binary::safeStrlen($pubKey)) {
            throw new Exception('invalid public key (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== Binary::safeSubstr($pubKey, 0, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }

        return [
            Binary::safeSubstr($pubKey, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_RANDOM_BYTES_LENGTH),
            Binary::safeSubstr($pubKey, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH),
        ];
    }

    /**
     * @param string $inputFile
     *
     * @return string
     */
    private static function getSecondLine($inputFile)
    {
        $fileLines = explode("\n", $inputFile);
        if (2 > \count($fileLines)) {
            throw new Exception('file does not contain >= 2 lines');
        }

        return $fileLines[1];
    }
}
