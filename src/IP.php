<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use GMP;
use LC\Portal\Exception\IPException;

/**
 * This class would be a lot simpler if only IPv4 existed with 32 bit
 * addresses.
 */
class IP
{
    public const IP_4 = 4;
    public const IP_6 = 6;

    private GMP $ipAddress;
    private int $ipPrefix;
    private int $ipFamily;

    private function __construct(GMP $ipAddress, int $ipPrefix, int $ipFamily)
    {
        $this->ipAddress = $ipAddress;
        $this->ipPrefix = $ipPrefix;
        $this->ipFamily = $ipFamily;
    }

    public function __toString(): string
    {
        return $this->address().'/'.$this->ipPrefix;
    }

    public static function fromIp(string $ipAddress, ?int $ipPrefix = null): self
    {
        if (false === strpos($ipAddress, ':')) {
            // IPv4
            return self::fromIpFour($ipAddress, $ipPrefix);
        }

        // IPv6
        return self::fromIpSix($ipAddress, $ipPrefix);
    }

    public static function fromIpPrefix(string $ipAddressPrefix): self
    {
        if (false === strpos($ipAddressPrefix, '/')) {
            // no prefix specified
            return self::fromIp($ipAddressPrefix);
        }

        [$ipAddress, $ipPrefix] = explode('/', $ipAddressPrefix, 2);

        return self::fromIp($ipAddress, (int) $ipPrefix);
    }

    public function address(): string
    {
        return self::toAddress($this->ipAddress, $this->addressBits());
    }

    public function network(): self
    {
        return self::fromIp(
            self::toAddress(
                gmp_and(
                    $this->ipAddress,
                    gmp_and(
                        gmp_sub(gmp_pow(2, $this->addressBits()), 1),
                        gmp_sub(gmp_pow(2, $this->addressBits()), gmp_pow(2, $this->addressBits() - $this->ipPrefix))
                    )
                ),
                $this->addressBits()
            ),
            $this->ipPrefix
        );
    }

    public function netmask(): string
    {
        return self::toAddress(
            gmp_xor(
                gmp_sub(gmp_pow(2, $this->addressBits()), 1),
                gmp_sub(gmp_pow(2, $this->addressBits() - $this->ipPrefix), 1),
            ),
            $this->addressBits()
        );
    }

    /**
     * Get the number of available IP addresses in the network represented by
     * the IP range specified in this object. Works only with IPv4 because we
     * want an "int" as the return type.
     */
    public function numberOfHosts(): int
    {
        if (self::IP_4 === $this->ipFamily) {
            // IPv4
            return (int) 2 ** (32 - $this->ipPrefix) - 2;
        }

        // IPv6
        if (0 === $intVal = gmp_intval(gmp_pow(2, (128 - $this->ipPrefix)))) {
            throw new IPException('too many hosts to fit in "int"');
        }

        return $intVal;
    }

    /**
     * Get the first usable IP in the network represented by the IP range
     * specified in this object.
     */
    public function firstHost(): string
    {
        if (self::IP_4 === $this->ipFamily && 31 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }
        if (self::IP_6 === $this->ipFamily && 127 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }

        return self::toAddress(
            gmp_add(
                self::fromAddress($this->network()->address()),
                1
            ),
            $this->addressBits()
        );
    }

    public function firstHostPrefix(): string
    {
        return $this->firstHost().'/'.$this->prefix();
    }

    /**
     * @return array<IP>
     */
    public function split(int $networkCount): array
    {
        // XXX introduce "maxPrefix" parameter?
        if (2 ** ($this->addressBits() - $this->ipPrefix - 2) < $networkCount) {
            throw new IPException('network too small to split in this many networks');
        }

        $requiredBits = (int) log($networkCount, 2);
        $prefixBits = self::IP_4 === $this->ipFamily ? $this->ipPrefix + $requiredBits : 112;
        if (self::IP_6 === $this->ipFamily) {
            $minPrefix = 112 - $requiredBits;
            if ($minPrefix < $this->ipPrefix) {
                throw new IPException('network too small, must be >= /'.$minPrefix);
            }
        }
        $netIp = $this->network();
        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            $noOfHosts = gmp_pow(2, $this->addressBits() - $prefixBits);
            $netAddress = gmp_add(gmp_mul($i, $noOfHosts), self::fromAddress($netIp->address()));
            $splitRanges[] = new self($netAddress, $prefixBits, $this->ipFamily);
        }

        return $splitRanges;
    }

    /**
     * @return array<string>
     */
    public function clientIpList(?int $maxNoOfHosts = null): array
    {
        // XXX use this code also to generate DNS (reverse)
        if (null === $maxNoOfHosts) {
            $maxNoOfHosts = $this->numberOfHosts() - 1;
        }

        if (self::IP_4 === $this->ipFamily && 31 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }
        if (self::IP_6 === $this->ipFamily && 127 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }

        $hostIpList = [];
        for ($i = 0; $i < $maxNoOfHosts; ++$i) {
            $hostIpList[] = self::toAddress(
                gmp_add(
                    self::fromAddress($this->network()->address()),
                    2 + $i
                ),
                $this->addressBits()
            );
        }

        return $hostIpList;
    }

    public function prefix(): int
    {
        return $this->ipPrefix;
    }

    public function family(): int
    {
        return $this->ipFamily;
    }

    private function addressBits(): int
    {
        return self::IP_4 === $this->ipFamily ? 32 : 128;
    }

    private static function fromIpFour(string $ipAddress, ?int $ipPrefix = null): self
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new IPException('invalid IPv4 address');
        }
        if (null === $ipPrefix) {
            $ipPrefix = 32;
        }
        if ($ipPrefix < 0 || $ipPrefix > 32) {
            throw new IPException('invalid IPv4 prefix');
        }

        return new self(self::fromAddress($ipAddress), $ipPrefix, self::IP_4);
    }

    private static function fromIpSix(string $ipAddress, ?int $ipPrefix = null): self
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new IPException('invalid IPv6 address');
        }
        if (null === $ipPrefix) {
            $ipPrefix = 128;
        }
        if ($ipPrefix < 0 || $ipPrefix > 128) {
            throw new IPException('invalid IPv6 prefix');
        }

        return new self(self::fromAddress($ipAddress), $ipPrefix, self::IP_6);
    }

    private static function fromAddress(string $ipAddress): GMP
    {
        return gmp_init(bin2hex(inet_pton($ipAddress)), 16);
    }

    private static function toAddress(GMP $ipAddress, int $addressBits): string
    {
        return inet_ntop(
            hex2bin(
                str_pad(
                    gmp_strval($ipAddress, 16),
                    (int) ($addressBits / 4),
                    '0',
                    STR_PAD_LEFT
                )
            )
        );
    }
}
