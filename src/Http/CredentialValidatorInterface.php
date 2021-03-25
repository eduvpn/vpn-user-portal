<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

interface CredentialValidatorInterface
{
    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass);
}
