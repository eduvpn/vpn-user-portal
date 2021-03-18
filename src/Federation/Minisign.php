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

/**
 * Validate Signify/Minisign signatures.
 *
 * @see https://jedisct1.github.io/minisign/
 */
class Minisign
{
    /** @var string */
    const SIGNIFY_ALGO_DESCRIPTION = 'Ed';

    /** @var int */
    const SIGNIFY_KEY_ID_LENGTH = 8;

    /** @var int */
    const ED_SIGNATURE_LENGTH = 64;

    /** @var int */
    const ED_PUBLIC_KEY_LENGTH = 32;

    /**
     * @param string        $messageText
     * @param string        $messageSignature
     * @param array<string> $encodedPublicKeyList
     *
     * XXX should we throw an exception always?
     *
     * @return bool
     */
    public static function verify($messageText, $messageSignature, array $encodedPublicKeyList)
    {
        $signatureData = self::getLine($messageSignature, 1);
        $msgSig = sodium_base642bin($signatureData, \SODIUM_BASE64_VARIANT_ORIGINAL);
        // <signature_algorithm> || <key_id> || <signature>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes, matching the public key
        //    signature (PureEdDSA): ed25519(<file data>)
        if (\strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH + self::ED_SIGNATURE_LENGTH !== \strlen($msgSig)) {
            throw new Exception('invalid signature (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== substr($msgSig, 0, \strlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }

        $signatureKeyId = substr($msgSig, \strlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_KEY_ID_LENGTH);
        // check whether we have a public key with this key ID
        $publicKey = self::getPublicKey($signatureKeyId, $encodedPublicKeyList);

        return sodium_crypto_sign_verify_detached(
            substr($msgSig, \strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH),
            $messageText,
            $publicKey
        );
    }

    /**
     * @param string        $signatureKeyId
     * @param array<string> $encodedPublicKeyList
     *
     * @return string
     */
    private static function getPublicKey($signatureKeyId, array $encodedPublicKeyList)
    {
        // <signature_algorithm> || <key_id> || <public_key>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes
        //    public_key: Ed25519 public key
        foreach ($encodedPublicKeyList as $encodedPublicKey) {
            $publicKey = sodium_base642bin($encodedPublicKey, \SODIUM_BASE64_VARIANT_ORIGINAL);
            if (\strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH + self::ED_PUBLIC_KEY_LENGTH !== \strlen($publicKey)) {
                throw new Exception('invalid public key (not long enough)');
            }
            if (self::SIGNIFY_ALGO_DESCRIPTION !== substr($publicKey, 0, \strlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
                throw new Exception('unsupported algorithm, we only support "Ed"');
            }
            $keyId = substr($publicKey, \strlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_KEY_ID_LENGTH);
            if ($keyId === $signatureKeyId) {
                return substr($publicKey, \strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_KEY_ID_LENGTH);
            }
        }

        throw new Exception('unable to find public key with requested key id');
    }

    /**
     * @param string $inputFile
     * @param int    $lineNo
     *
     * @return string
     */
    private static function getLine($inputFile, $lineNo)
    {
        $fileLines = explode("\n", $inputFile);
        if ($lineNo > \count($fileLines)) {
            throw new Exception('file does not contain >= '.$lineNo.' lines');
        }

        return $fileLines[$lineNo];
    }
}
