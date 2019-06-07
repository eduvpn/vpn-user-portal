<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

interface ServerManagerInterface
{
    /**
     * @return array<string,array>
     */
    public function connections(): array;

    public function kill(string $commonName): int;
}
