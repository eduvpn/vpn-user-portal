<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use Vpn\Portal\TplInterface;

class JsonTpl implements TplInterface
{
    /**
     * @param array<string,mixed> $templateVariables
     */
    public function addDefault(array $templateVariables): void
    {
    }

    /**
     * @param array<string,mixed> $templateVariables
     */
    public function render(string $templateName, array $templateVariables = []): string
    {
        return json_encode([$templateName => $templateVariables]);
    }
}
