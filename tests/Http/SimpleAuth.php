<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class SimpleAuth implements CredentialValidatorInterface
{
    /** @var array<string,string> */
    private $userPass;

    /**
     * @param array<string,string> $userPass
     */
    public function __construct(array $userPass)
    {
        $this->userPass = $userPass;
    }

    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        if (!\array_key_exists($authUser, $this->userPass)) {
            return false;
        }

        if (!password_verify($authPass, $this->userPass[$authUser])) {
            return false;
        }

        return new UserInfo($authUser, []);
    }
}
