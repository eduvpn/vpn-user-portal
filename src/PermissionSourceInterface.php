<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

interface PermissionSourceInterface
{
    /**
     * Get current attributes for users directly from the source.
     *
     * If no attributes are available, or the user no longer exists, an empty
     * array is returned.
     *
     * @return array<string>
     */
    public function attributesForUser(string $userId): array;
}
