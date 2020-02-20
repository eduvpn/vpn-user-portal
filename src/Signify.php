<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use Exception;

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
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
        $msgSig = base64_decode($signatureData, true);
        // <signature_algorithm> || <key_id> || <signature>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes, matching the public key
        //    signature (PureEdDSA): ed25519(<file data>)
        if (\strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH + self::ED_SIGNATURE_LENGTH !== \strlen($msgSig)) {
            throw new Exception('invalid signature (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== substr($msgSig, 0, \strlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }
        if ($this->keyId !== substr($msgSig, \strlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_RANDOM_BYTES_LENGTH)) {
            throw new Exception('signature does not match public key');
        }

        return sodium_crypto_sign_verify_detached(
            substr($msgSig, \strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH),
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
        $pubKey = base64_decode($publicKeyData, true);
        // <signature_algorithm> || <key_id> || <public_key>
        //    signature_algorithm: Ed
        //    key_id: 8 random bytes
        //    public_key: Ed25519 public key
        if (\strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH + self::ED_PUBLIC_KEY_LENGTH !== \strlen($pubKey)) {
            throw new Exception('invalid public key (not long enough)');
        }
        if (self::SIGNIFY_ALGO_DESCRIPTION !== substr($pubKey, 0, \strlen(self::SIGNIFY_ALGO_DESCRIPTION))) {
            throw new Exception('unsupported algorithm, we only support "Ed"');
        }

        return [
            substr($pubKey, \strlen(self::SIGNIFY_ALGO_DESCRIPTION), self::SIGNIFY_RANDOM_BYTES_LENGTH),
            substr($pubKey, \strlen(self::SIGNIFY_ALGO_DESCRIPTION) + self::SIGNIFY_RANDOM_BYTES_LENGTH),
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
