<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

use DateInterval;
use Vpn\Portal\Crypto\Minisign\PublicKey;

class ApiConfig
{
    use ConfigTrait;

    public const DEFAULT_TOKEN_EXPIRY = 'PT1H';
    public const DEFAULT_APP_GONE_INTERVAL = 'PT72H';
    public const DEFAULT_GUEST_ACCESS_SERVER_LIST_URL = 'https://disco.eduvpn.org/v2/server_list.json';
    public const DEFAULT_GUEST_ACCESS_SERVER_LIST_SIGNATURE_URL = 'https://disco.eduvpn.org/v2/server_list.json.minisig';
    public const DEFAULT_GUEST_ACCESS_PUBLIC_KEY_LIST = [
        'RWQKqtqvd0R7rUDp0rWzbtYPA3towPWcLDCl7eY9pBMMI/ohCmrS0WiM',
        'RWRtBSX1alxyGX+Xn3LuZnWUT0w//B6EmTJvgaAxBMYzlQeI+jdrO6KF',
    ];

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * OAuth "access_token" expiry.
     */
    public function tokenExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('tokenExpiry', self::DEFAULT_TOKEN_EXPIRY));
    }

    public function maxActiveConfigurations(): int
    {
        return $this->requireInt('maxActiveConfigurations', 3);
    }

    /**
     * The interval after which to consider an API client gone without
     * any activity.
     *
     * This is used to clean up WireGuard IP allocations for clients that are
     * most likely permanently gone and did not call the "/disconnect" API.
     */
    public function appGoneInterval(): DateInterval
    {
        return new DateInterval($this->requireString('appGoneInterval', self::DEFAULT_APP_GONE_INTERVAL));
    }

    public function deleteAuthorizationOnDisconnect(): bool
    {
        return $this->requireBool('deleteAuthorizationOnDisconnect', false);
    }

    public function enableGuestAccess(): bool
    {
        return $this->requireBool('enableGuestAccess', false);
    }

    public function guestAccessServerListUrl(): string
    {
        return $this->requireString('guestAccessServerListUrl', self::DEFAULT_GUEST_ACCESS_SERVER_LIST_URL);
    }

    public function guestAccessServerListSignatureUrl(): string
    {
        return $this->requireString('guestAccessServerListSignatureUrl', self::DEFAULT_GUEST_ACCESS_SERVER_LIST_SIGNATURE_URL);
    }

    /**
     * @return array<\Vpn\Portal\Crypto\Minisign\PublicKey>
     */
    public function guestAccessPublicKeyList(): array
    {
        $guestAccessPublicKeyList = [];
        foreach ($this->requireStringArray('guestAccessPublicKeyList', self::DEFAULT_GUEST_ACCESS_PUBLIC_KEY_LIST) as $publicKey) {
            $guestAccessPublicKeyList[] = new PublicKey($publicKey);
        }

        return $guestAccessPublicKeyList;
    }
}
