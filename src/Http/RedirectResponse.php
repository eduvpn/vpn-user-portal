<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

class RedirectResponse extends Response
{
    public function __construct(string $redirectUri, int $statusCode = 302)
    {
        parent::__construct(null, ['Location' => $redirectUri], $statusCode);
    }
}
