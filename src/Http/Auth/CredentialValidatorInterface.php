<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Http\UserInfo;

interface CredentialValidatorInterface
{
    /**
     * @throws \Vpn\Portal\Http\Auth\Exception\CredentialValidatorException
     */
    public function validate(string $authUser, string $authPass): UserInfo;
}
