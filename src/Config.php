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

/**
 * XXX finish getting rid of generic methods.
 */
class Config
{
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
        return new ApiConfig($this->s('Api'));
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

    public function vpnDaemonTls(): bool
    {
        return $this->requireBool('vpnDaemonTls', true);
    }

    public function defaultLanguage(): string
    {
        return $this->requireString('defaultLanguage', 'en-US');
    }

    public function styleName(): ?string
    {
        return $this->optionalString('styleName');
    }

    public function showPermissions(): bool
    {
        return $this->requireBool('showPermissions', false);
    }

    public function profileConfig(string $profileId): ProfileConfig
    {
        return new ProfileConfig(
            new self(
                array_merge(
                    ['profileId' => $profileId],
                    $this->s('vpnProfiles')->requireArray($profileId)
                )
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
                new self(
                    array_merge(
                        ['profileId' => $profileId],
                        $profileData
                    )
                )
            );
        }

        return $profileConfigList;
    }

    public function s(string $k): self
    {
        if (!\array_key_exists($k, $this->configData)) {
            return new self([]);
        }
        if (!\is_array($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type array');
        }

        return new self($this->configData[$k]);
    }

    public function optionalString(string $k): ?string
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_string($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type string');
        }

        return $this->configData[$k];
    }

    public function requireString(string $k, ?string $d = null): string
    {
        if (null === $v = $this->optionalString($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    public function optionalInt(string $k): ?int
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_int($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type int');
        }

        return $this->configData[$k];
    }

    public function requireInt(string $k, ?int $d = null): int
    {
        if (null === $v = $this->optionalInt($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    public function optionalBool(string $k): ?bool
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_bool($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type bool');
        }

        return $this->configData[$k];
    }

    public function requireBool(string $k, ?bool $d = null): bool
    {
        if (null === $v = $this->optionalBool($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    public function optionalArray(string $k): ?array
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_array($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type array');
        }

        return $this->configData[$k];
    }

    public function requireArray(string $k, array $d = null): array
    {
        if (null === $v = $this->optionalArray($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
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

    private function toArray(): array
    {
        return $this->configData;
    }
}
