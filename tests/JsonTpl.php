<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal\Tests;

use SURFnet\VPN\Common\TplInterface;

class JsonTpl implements TplInterface
{
    /**
     * @param array $templateVariables
     *
     * @return void
     */
    public function addDefault(array $templateVariables)
    {
    }

    /**
     * @param string $templateName
     * @param array  $templateVariables
     *
     * @return string
     */
    public function render($templateName, array $templateVariables)
    {
        return json_encode([$templateName => $templateVariables]);
    }
}
