<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\Exception\ConfigException;

trait ConfigTrait
{
    private function s(string $k): self
    {
        return new self($this->requireArray($k, []));
    }

    private function optionalString(string $k): ?string
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_string($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type string');
        }

        return $this->configData[$k];
    }

    private function requireString(string $k, ?string $d = null): string
    {
        if (null === $v = $this->optionalString($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    private function optionalInt(string $k): ?int
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_int($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type int');
        }

        return $this->configData[$k];
    }

    private function requireInt(string $k, ?int $d = null): int
    {
        if (null === $v = $this->optionalInt($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    private function optionalBool(string $k): ?bool
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_bool($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type bool');
        }

        return $this->configData[$k];
    }

    private function requireBool(string $k, ?bool $d = null): bool
    {
        if (null === $v = $this->optionalBool($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    private function optionalArray(string $k): ?array
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_array($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type array');
        }

        return $this->configData[$k];
    }

    private function requireArray(string $k, ?array $d = null): array
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
     * @return array<int>
     */
    private function optionalIntArray(string $k): ?array
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_array($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type array');
        }

        if ($this->configData[$k] !== array_filter($this->configData[$k], 'is_int')) {
            throw new ConfigException('key "'.$k.'" not of type array<int>');
        }

        return $this->configData[$k];
    }

    /**
     * @return array<int>
     */
    private function requireIntArray(string $k, ?array $d = null): array
    {
        if (null === $v = $this->optionalIntArray($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    /**
     * @return array<string>
     */
    private function optionalStringArray(string $k): ?array
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        if (!\is_array($this->configData[$k])) {
            throw new ConfigException('key "'.$k.'" not of type array');
        }

        if ($this->configData[$k] !== array_filter($this->configData[$k], 'is_string')) {
            throw new ConfigException('key "'.$k.'" not of type array<string>');
        }

        return $this->configData[$k];
    }

    /**
     * @return array<string>
     */
    private function requireStringArray(string $k, ?array $d = null): array
    {
        if (null === $v = $this->optionalStringArray($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    /**
     * @return array<string>
     */
    private function requireStringOrStringArray(string $k, ?array $d = null): array
    {
        if (null === $v = $this->optionalStringOrStringArray($k)) {
            if (null !== $d) {
                return $d;
            }

            throw new ConfigException('key "'.$k.'" not available');
        }

        return $v;
    }

    /**
     * @return array<string>
     */
    private function optionalStringOrStringArray(string $k): ?array
    {
        if (!\array_key_exists($k, $this->configData)) {
            return null;
        }
        $this->configData[$k] = (array) $this->configData[$k];

        return $this->requireStringArray($k);
    }

    private function toArray(): array
    {
        return $this->configData;
    }
}
