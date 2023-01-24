<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Http\Auth\Exception\CredentialValidatorException;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\Storage;

class DbCredentialValidator implements CredentialValidatorInterface
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @throws \Vpn\Portal\Http\Auth\Exception\CredentialValidatorException
     */
    public function validate(string $authUser, string $authPass): UserInfo
    {
        if (null === $passwordHash = $this->storage->localUserPasswordHash($authUser)) {
            throw new CredentialValidatorException('no such user');
        }

        // even though we switched to libsodium `sodium_crypto_pwhash_str` to
        // generate the password hashes, we can keep using PHP's
        // password_verify to both verify the "legacy" bcrypt hashes and the
        // new Argon2ID hashes
        if (password_verify($authPass, $passwordHash)) {
            return new UserInfo($authUser, []);
        }

        throw new CredentialValidatorException('invalid password');
    }
}
