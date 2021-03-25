<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

interface SessionInterface
{
    public function regenerate(): void;

    public function get(string $sessionKey): ?string;

    public function set(string $sessionKey, string $sessionValue): void;

    public function remove(string $sessionKey): void;

    public function destroy(): void;
}
