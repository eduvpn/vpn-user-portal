<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class ProfileConfig
{
    use ConfigTrait;

    public function profileId(): string
    {
        return $this->requireString('profileId');
    }

    public function vpnProto(): string
    {
        return $this->requireString('vpnProto');
    }

    public function profileNumber(): int
    {
        return $this->requireInt('profileNumber');
    }

    public function hostName(): string
    {
        return $this->requireString('hostName');
    }

    public function range(): string
    {
        return $this->requireString('range');
    }

    public function range6(): string
    {
        return $this->requireString('range6');
    }

    public function displayName(): string
    {
        return $this->requireString('displayName');
    }

    public function defaultGateway(): bool
    {
        return $this->requireBool('defaultGateway', true);
    }

    /**
     * @return array<string>
     */
    public function routes(): array
    {
        return $this->requireArray('routes', []);
    }

    /**
     * @return array<string>
     */
    public function dns(): array
    {
        return $this->requireArray('dns', []);
    }

    public function clientToClient(): bool
    {
        return $this->requireBool('clientToClient', false);
    }

    public function listenIp(): string
    {
        return $this->requireString('listenIp', '::');
    }

    public function enableLog(): bool
    {
        return $this->requireBool('enableLog', false);
    }

    public function enableAcl(): bool
    {
        return $this->requireBool('enableAcl', false);
    }

    /**
     * @return array<string>
     */
    public function aclPermissionList(): array
    {
        return $this->requireArray('aclPermissionList', []);
    }

    public function nodeIp(): string
    {
        return $this->requireString('nodeIp', '127.0.0.1');
    }

    /**
     * @return array<string>
     */
    public function vpnProtoPorts(): array
    {
        if ('wireguard' === $this->vpnProto()) {
            // for WireGuard we have only one port for all profiles
            return ['udp/51820'];
        }

        return $this->requireArray('vpnProtoPorts', ['udp/1194', 'tcp/1194']);
    }

    /**
     * @return array<string>
     */
    public function exposedVpnProtoPorts(): array
    {
        return $this->requireArray('exposedVpnProtoPorts', []);
    }

    public function hideProfile(): bool
    {
        return $this->requireBool('hideProfile', false);
    }

    public function blockLan(): bool
    {
        return $this->requireBool('blockLan', false);
    }

    public function dnsDomain(): ?string
    {
        return $this->optionalString('dnsDomain');
    }

    /**
     * @return array<string>
     */
    public function dnsDomainSearch(): array
    {
        return $this->requireArray('dnsDomainSearch', []);
    }
}
