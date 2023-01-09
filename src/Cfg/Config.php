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
use Vpn\Portal\Cfg\Exception\ConfigException;
use Vpn\Portal\FileIO;

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

    public function browserSessionExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('browserSessionExpiry', 'PT30M'));
    }

    public function caExpiry(): DateInterval
    {
        return new DateInterval($this->requireString('caExpiry', 'P10Y'));
    }

    public function maxActiveConfigurations(): int
    {
        return $this->requireInt('maxActiveConfigurations', 3);
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

    public function showPermissions(): bool
    {
        return $this->requireBool('showPermissions', false);
    }

    public function connectScriptPath(): ?string
    {
        return $this->optionalString('connectScriptPath');
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
     * Return the list of all UNIQUE "nodeUrls" used by the profile(s).
     *
     * @return array<int,string>
     */
    public function nodeNumberUrlList(): array
    {
        $nodeNumberUrlList = [];
        foreach ($this->profileConfigList() as $profileConfig) {
            foreach ($profileConfig->onNode() as $nodeNumber) {
                $nodeUrl = $profileConfig->nodeUrl($nodeNumber);
                if (!in_array($nodeUrl, $nodeNumberUrlList, true)) {
                    $nodeNumberUrlList[$nodeNumber] = $nodeUrl;
                }
            }
        }

        return $nodeNumberUrlList;
    }

    /**
     * @return array<ProfileConfig>
     */
    public function profileConfigList(): array
    {
        $profileConfigList = [];
        foreach ($this->s('ProfileList')->toArray() as $profileData) {
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

    public function logConfig(): LogConfig
    {
        return new LogConfig($this->s('Log')->toArray());
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

    public function oidcAuthConfig(): OidcAuthConfig
    {
        return new OidcAuthConfig($this->s('OidcAuthModule')->toArray());
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
