<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

interface TplInterface
{
    /**
     * @param mixed $v
     */
    public function addDefault(string $k, $v): void;

    /**
     * @param array<string,mixed> $templateVariables
     */
    public function render(string $templateName, array $templateVariables = []): string;
}
