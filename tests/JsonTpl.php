<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Common\TplInterface;

class JsonTpl implements TplInterface
{
    /**
     * @param array<string,mixed> $templateVariables
     */
    public function addDefault(array $templateVariables): void
    {
    }

    /**
     * @param string              $templateName
     * @param array<string,mixed> $templateVariables
     *
     * @return string
     */
    public function render($templateName, array $templateVariables = [])
    {
        return json_encode([$templateName => $templateVariables]);
    }
}
