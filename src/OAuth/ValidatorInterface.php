<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OAuth;

use fkooman\OAuth\Server\AccessToken;
use fkooman\OAuth\Server\Http\Request;

interface ValidatorInterface
{
    public function validate(?Request $request = null): AccessToken;
}
