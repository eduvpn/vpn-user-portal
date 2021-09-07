<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Federation;

use Exception;
use LC\Portal\Base64;
use LC\Portal\Binary;

/**
 * Validate Signify/Minisign signatures.
 *
 * @see https://jedisct1.github.io/minisign/
 */
class Minisign
{
    /** @var string */
    public const SIGNIFY_ALGO_DESCRIPTION = 'Ed';

    /** @var int */
    public const SIGNIFY_KEY_ID_LENGTH = 8;

    /** @var int */
    public const ED_SIGNATURE_LENGTH = 64;

    /** @var int */
    public const ED_PUBLIC_KEY_LENGTH = 32;

    /**
     * @param array<string> $encodedPublicKeyList
     *
     * XXX should we throw an exception always?
     *
     * @return bool
     */
    public static function verify(string $messageText, string $messageSignature, array $encodedPublicKeyList)
    {
        $signatureData = self::getLine($messageSignature, 1);
        $msgSig = Base64::decode($signatureData);
        // <signature_algorithm> || <key_id> || <signature>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes, matching the public key
        //    signature (PureEdDSA): ed25519(<file data>)
        if (Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH + self::ED_SIGNATURE_LENGTH !== Binary::safeStrlen($msgSig)) {
            throw new Exception('invalid signature (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== Binary::safeSubstr($msgSig, 0, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }

        $signatureKeyId = Binary::safeSubstr($msgSig, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_KEY_ID_LENGTH);
        // check whether we have a public key with this key ID
        $publicKey = self::getPublicKey($signatureKeyId, $encodedPublicKeyList);

        return sodium_crypto_sign_verify_detached(
            Binary::safeSubstr($msgSig, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH),
            $messageText,
            $publicKey
        );
    }

    /**
     * @param array<string> $encodedPublicKeyList
     */
    private static function getPublicKey(string $signatureKeyId, array $encodedPublicKeyList): string
    {
        // <signature_algorithm> || <key_id> || <public_key>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes
        //    public_key: Ed25519 public key
        foreach ($encodedPublicKeyList as $encodedPublicKey) {
            $publicKey = Base64::decode($encodedPublicKey);
            if (Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH + self::ED_PUBLIC_KEY_LENGTH !== Binary::safeStrlen($publicKey)) {
                throw new Exception('invalid public key (not long enough)');
            }
            if (self::SIGNIFY_ALGO_DESCRIPTION !== Binary::safeSubstr($publicKey, 0, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
                throw new Exception('unsupported algorithm, we only support "Ed"');
            }
            $keyId = Binary::safeSubstr($publicKey, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_KEY_ID_LENGTH);
            if ($keyId === $signatureKeyId) {
                return Binary::safeSubstr($publicKey, Binary::safeStrlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH);
            }
        }

        throw new Exception('unable to find public key with requested key id');
    }

    private static function getLine(string $inputFile, int $lineNo): string
    {
        $fileLines = explode("\n", $inputFile);
        if ($lineNo > \count($fileLines)) {
            throw new Exception('file does not contain >= '.$lineNo.' lines');
        }

        return $fileLines[$lineNo];
    }
}
