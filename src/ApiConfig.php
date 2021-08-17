<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;

class ApiConfig
{
    use ConfigTrait;

    public function remoteAccess(): bool
    {
        return $this->requireBool('remoteAccess', false);
    }

    public function tokenExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('tokenExpiry', 'PT1H'));
    }
}
