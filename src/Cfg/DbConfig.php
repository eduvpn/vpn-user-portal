<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

class DbConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function baseDir(): string
    {
        return $this->requireString('baseDir');
    }

    public function schemaDir(): string
    {
        return $this->baseDir().'/schema';
    }

    public function dbDsn(): string
    {
        return $this->requireString('dbDsn', 'sqlite://'.$this->baseDir().'/data/db.sqlite');
    }

    public function dbUser(): ?string
    {
        return $this->optionalString('dbUser');
    }

    public function dbPass(): ?string
    {
        return $this->optionalString('dbPass');
    }
}
