<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateInterval;
use Vpn\Portal\Exception\ConfigException;

class Config
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function sessionExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('sessionExpiry', 'P90D'));
    }

    public function caExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('caExpiry', 'P10Y'));
    }

    public function maxActiveConfigurations(): ?int
    {
        return $this->optionalInt('maxActiveConfigurations');
    }

    public function secureCookie(): bool
    {
        return $this->requireBool('secureCookie', true);
    }

    public function apiConfig(): ApiConfig
    {
        return new ApiConfig($this->s('Api')->toArray());
    }

    public function memcacheSessionConfig(): MemcacheSessionConfig
    {
        return new MemcacheSessionConfig($this->s('MemcacheSessionModule')->toArray());
    }

    public function vpnCaPath(): string
    {
        return $this->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    }

    public function authModule(): string
    {
        return $this->requireString('authModule', 'DbAuthModule');
    }

    public function sessionModule(): string
    {
        return $this->requireString('sessionModule', 'FileSessionModule');
    }

    public function defaultLanguage(): string
    {
        return $this->requireString('defaultLanguage', 'en-US');
    }

    /**
     * @return ?array<string>
     */
    public function accessPermissionList(): ?array
    {
        return $this->optionalStringArray('accessPermissionList');
    }

    /**
     * @return array<string>
     */
    public function adminUserIdList(): array
    {
        return $this->requireStringArray('adminUserIdList', []);
    }

    /**
     * @return array<string>
     */
    public function adminPermissionList(): array
    {
        return $this->requireStringArray('adminPermissionList', []);
    }

    /**
     * @return array<string>
     */
    public function enabledLanguages(): array
    {
        return $this->requireStringArray('enabledLanguages', ['en-US']);
    }

    public function styleName(): ?string
    {
        return $this->optionalString('styleName');
    }

    public function connectionLogFormat(): string
    {
        return $this->requireString('connectionLogFormat', '{{EVENT_TYPE}} {{USER_ID}} ({{PROFILE_ID}}:{{CONNECTION_ID}}) [{{IP_FOUR}},{{IP_SIX}}]');
    }

    public function showPermissions(): bool
    {
        return $this->requireBool('showPermissions', false);
    }

    public function wireGuardConfig(): WireGuardConfig
    {
        return new WireGuardConfig($this->s('WireGuard')->toArray());
    }

    public function hasProfile(string $profileId): bool
    {
        foreach ($this->profileConfigList() as $profileConfig) {
            if ($profileId === $profileConfig->profileId()) {
                return true;
            }
        }

        return false;
    }

    public function profileConfig(string $profileId): ProfileConfig
    {
        foreach ($this->profileConfigList() as $profileConfig) {
            if ($profileId === $profileConfig->profileId()) {
                return $profileConfig;
            }
        }

        throw new ConfigException('profile "'.$profileId.'" does not exist');
    }

    /**
     * @return array<ProfileConfig>
     */
    public function profileConfigList(): array
    {
        $profileConfigList = [];
        foreach ($this->s('vpnProfiles')->toArray() as $profileData) {
            $profileConfigList[] = new ProfileConfig($profileData);
        }

        return $profileConfigList;
    }

    public function dbConfig(string $baseDir): DbConfig
    {
        return new DbConfig(
            array_merge(
                [
                    'baseDir' => $baseDir,
                ],
                $this->s('Db')->toArray()
            )
        );
    }

    public function mellonAuthConfig(): MellonAuthConfig
    {
        return new MellonAuthConfig($this->s('MellonAuthModule')->toArray());
    }

    public function phpSamlSpAuthConfig(): PhpSamlSpAuthConfig
    {
        return new PhpSamlSpAuthConfig($this->s('PhpSamlSpAuthModule')->toArray());
    }

    public function shibAuthConfig(): ShibAuthConfig
    {
        return new ShibAuthConfig($this->s('ShibAuthModule')->toArray());
    }

    public function radiusAuthConfig(): RadiusAuthConfig
    {
        return new RadiusAuthConfig($this->s('RadiusAuthModule')->toArray());
    }

    public function ldapAuthConfig(): LdapAuthConfig
    {
        return new LdapAuthConfig($this->s('LdapAuthModule')->toArray());
    }

    /**
     * @psalm-suppress UnresolvableInclude
     */
    public static function fromFile(string $configFile): self
    {
        if (false === FileIO::exists($configFile)) {
            throw new ConfigException(sprintf('unable to read "%s"', $configFile));
        }

        return new self(require $configFile);
    }
}
