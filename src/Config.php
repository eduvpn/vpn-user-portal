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
use LC\Portal\Exception\ConfigException;

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

    public function secureCookie(): bool
    {
        return $this->requireBool('secureCookie', true);
    }

    public function apiConfig(): ApiConfig
    {
        return new ApiConfig($this->s('Api')->toArray());
    }

    public function vpnCaPath(): string
    {
        return $this->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    }

    public function authModule(): string
    {
        return $this->requireString('authModule', 'DbAuthModule');
    }

    public function enableConfigDownload(): bool
    {
        return $this->requireBool('enableConfigDownload', true);
    }

    public function defaultLanguage(): string
    {
        return $this->requireString('defaultLanguage', 'en-US');
    }

    public function styleName(): ?string
    {
        return $this->optionalString('styleName');
    }

    public function connectionLogFormat(): string
    {
        return $this->requireString('connectionLogFormat', '{{EVENT_TYPE}} {{USER_ID}} ({{PROFILE_ID}}) [{{IP_FOUR}},{{IP_SIX}}]');
    }

    public function showPermissions(): bool
    {
        return $this->requireBool('showPermissions', false);
    }

    public function wgPort(): int
    {
        return $this->requireInt('wgPort', 51820);
    }

    public function hasProfile(string $profileId): bool
    {
        return null !== $this->s('vpnProfiles')->optionalArray($profileId);
    }

    public function profileConfig(string $profileId): ProfileConfig
    {
        if (null === $profileData = $this->s('vpnProfiles')->optionalArray($profileId)) {
            throw new ConfigException('profile "'.$profileId.'" does not exist');
        }

        return new ProfileConfig(
            array_merge(
                [
                    'profileId' => $profileId,
                ],
                $profileData
            )
        );
    }

    /**
     * @return array<ProfileConfig>
     */
    public function profileConfigList(): array
    {
        $profileConfigList = [];
        foreach ($this->s('vpnProfiles')->toArray() as $profileId => $profileData) {
            $profileConfigList[] = new ProfileConfig(
                array_merge(
                    [
                        'profileId' => $profileId,
                    ],
                    $profileData
                )
            );
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
