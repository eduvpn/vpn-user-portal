<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\ConfigException;

trait ConfigTrait
{
    protected array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
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

    public function s(string $k): self
    {
        return new self($this->requireArray($k));
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

    protected function toArray(): array
    {
        return $this->configData;
    }
}
