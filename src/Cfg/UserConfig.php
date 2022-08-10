<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;


class UserConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }


    public function connectionExpiresAt(): bool
    {
        return $this->requireBool('connectionExpiresAt', false);
    }

    public function maxActiveConfigurations(): bool
    {
        return $this->requireBool('maxActiveConfigurations', false);
    }

    public function maxActiveApiConfigurations(): bool
    {
        return $this->requireBool('maxActiveApiConfigurations', false);
    }

}
