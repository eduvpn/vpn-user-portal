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
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function profileId(): string
    {
        return $this->config->requireString('profileId');
    }

    public function vpnType(): string
    {
        return $this->config->requireString('vpnType');
    }

    public function profileNumber(): int
    {
        return $this->config->requireInt('profileNumber');
    }

    public function hostName(): string
    {
        return $this->config->requireString('hostName');
    }

    public function range(): string
    {
        return $this->config->requireString('range');
    }

    public function range6(): string
    {
        return $this->config->requireString('range6');
    }

    public function displayName(): string
    {
        return $this->config->requireString('displayName');
    }

    public function defaultGateway(): bool
    {
        return $this->config->requireBool('defaultGateway', true);
    }

    /**
     * @return array<string>
     */
    public function routes(): array
    {
        return $this->config->requireArray('routes', []);
    }

    /**
     * @return array<string>
     */
    public function dns(): array
    {
        return $this->config->requireArray('dns', []);
    }

    public function clientToClient(): bool
    {
        return $this->config->requireBool('clientToClient', false);
    }

    public function listenIp(): string
    {
        return $this->config->requireString('listenIp', '::');
    }

    public function enableLog(): bool
    {
        return $this->config->requireBool('enableLog', false);
    }

    public function enableAcl(): bool
    {
        return $this->config->requireBool('enableAcl', false);
    }

    /**
     * @return array<string>
     */
    public function aclPermissionList(): array
    {
        return $this->config->requireArray('aclPermissionList', []);
    }

    public function nodeIp(): string
    {
        return $this->config->requireString('nodeIp', '127.0.0.1');
    }

    /**
     * @return array<string>
     */
    public function vpnProtoPorts(): array
    {
        return $this->config->requireArray('vpnProtoPorts', ['udp/1194', 'tcp/1194']);
    }

    /**
     * @return array<string>
     */
    public function exposedVpnProtoPorts(): array
    {
        return $this->config->requireArray('exposedVpnProtoPorts', []);
    }

    public function hideProfile(): bool
    {
        return $this->config->requireBool('hideProfile', false);
    }

    public function blockLan(): bool
    {
        return $this->config->requireBool('blockLan', false);
    }

    public function dnsDomain(): ?string
    {
        return $this->config->optionalString('dnsDomain');
    }

    /**
     * @return array<string>
     */
    public function dnsDomainSearch(): array
    {
        return $this->config->requireArray('dnsDomainSearch', []);
    }
}
