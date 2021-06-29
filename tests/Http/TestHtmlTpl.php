<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\TplInterface;

class TestHtmlTpl implements TplInterface
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
        $str = '<html><head><title>{{code}}</title></head><body><h1>Error ({{code}})</h1><p>{{message}}</p></body></html>';
        foreach ($templateVariables as $k => $v) {
            $str = str_replace(sprintf('{{%s}}', $k), $v, $str);
        }

        return $str;
    }
}