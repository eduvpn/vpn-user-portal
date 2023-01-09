<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use GMP;
use Vpn\Portal\Exception\IpException;

/**
 * This class would be a lot simpler if only IPv4 existed with 32 bit
 * addresses :-).
 */
class Ip
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
     * the IP range specified in this object.
     */
    public function numberOfHostsFour(): int
    {
        if (self::IP_4 !== $this->ipFamily) {
            throw new IpException('IPv4 only');
        }

        return 2 ** (32 - $this->ipPrefix) - 2;
    }

    /**
     * Get the first usable IP in the network represented by the IP range
     * specified in this object.
     */
    public function firstHost(): string
    {
        if (self::IP_4 === $this->ipFamily && 31 <= $this->ipPrefix) {
            throw new IpException('network not big enough');
        }
        if (self::IP_6 === $this->ipFamily && 127 <= $this->ipPrefix) {
            throw new IpException('network not big enough');
        }

        return self::toAddress(
            gmp_add(
                self::fromAddress($this->network()->address()),
                1
            ),
            $this->addressBits()
        );
    }

    public function lastHost(): string
    {
        return self::toAddress(
            gmp_add(
                self::fromAddress($this->network()->address()),
                gmp_sub(
                    gmp_pow(2, $this->addressBits() - $this->prefix()),
                    1
                )
            ),
            $this->addressBits()
        );
    }

    public function firstHostPrefix(): string
    {
        return $this->firstHost().'/'.$this->prefix();
    }

    /**
     * @return array{0:Ip,1:Ip}
     */
    public function splitInHalf(): array
    {
        if (self::IP_4 === $this->ipFamily && $this->ipPrefix > 31) {
            throw new IpException(sprintf('can not split prefix "/%d"', $this->ipPrefix));
        }

        if (self::IP_6 === $this->ipFamily && $this->ipPrefix > 127) {
            throw new IpException(sprintf('can not split prefix "/%d"', $this->ipPrefix));
        }

        $prefixBits = $this->ipPrefix + 1;
        $netIp = $this->network();
        $noOfHosts = gmp_pow(2, $this->addressBits() - $prefixBits);

        return [
            new self(
                self::fromAddress($netIp->address()),
                $prefixBits,
                $this->ipFamily
            ),
            new self(
                gmp_add($noOfHosts, self::fromAddress($netIp->address())),
                $prefixBits,
                $this->ipFamily
            ),
        ];
    }

    /**
     * @return array<Ip>
     */
    public function split(int $networkCount): array
    {
        // XXX what if we split in three?!
        // XXX introduce "maxPrefix" parameter?
        if (2 ** ($this->addressBits() - $this->ipPrefix - 2) < $networkCount) {
            throw new IpException('network too small to split in this many networks');
        }

        $requiredBits = (int) log($networkCount, 2);
        $prefixBits = self::IP_4 === $this->ipFamily ? $this->ipPrefix + $requiredBits : 112;
        if (self::IP_6 === $this->ipFamily) {
            $minPrefix = 112 - $requiredBits;
            if ($minPrefix < $this->ipPrefix) {
                throw new IpException('network too small, must be >= /'.$minPrefix);
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
    public function clientIpListFour(): array
    {
        if (self::IP_4 !== $this->ipFamily) {
            throw new IpException('IPv4 only');
        }

        if (31 <= $this->ipPrefix) {
            throw new IpException('network not big enough to create a list of client IPs');
        }

        $maxNoOfHosts = 2 ** (32 - $this->ipPrefix) - 3;
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

    /**
     * @return array<string>
     */
    public function clientIpListSix(int $maxNoOfHosts): array
    {
        if (self::IP_6 !== $this->ipFamily) {
            throw new IpException('IPv6 only');
        }

        if (127 <= $this->ipPrefix) {
            throw new IpException('network not big enough to create a list of client IPs');
        }

        // make sure we do not specify a "maxNumberOfHosts" that is bigger
        // than the prefix we have
        $prefixMaxNoOfHosts = gmp_sub(gmp_pow(2, 128 - $this->ipPrefix), 3);
        if (gmp_cmp($maxNoOfHosts, $prefixMaxNoOfHosts) > 0) {
            throw new IpException(sprintf('prefix "/%d" does not contain "%d" hosts', $this->ipPrefix, $maxNoOfHosts));
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

    public function equals(self $i): bool
    {
        return $this->address() === $i->address() && $this->prefix() === $i->prefix();
    }

    public function contains(self $i): bool
    {
        if ($this->family() !== $i->family()) {
            return false;
        }

        // the first address of the range
        $lowerAddress = self::fromAddress($this->network()->address());
        // the last address of the range
        $upperAddress = self::fromAddress($this->network()->lastHost());

        $lowerCompare = gmp_cmp(self::fromAddress($i->network()->address()), $lowerAddress);
        $upperCompare = gmp_cmp(self::fromAddress($i->network()->lastHost()), $upperAddress);

        return $lowerCompare >= 0 && $upperCompare <= 0;
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
            throw new IpException('invalid IPv4 address');
        }
        if (null === $ipPrefix) {
            $ipPrefix = 32;
        }
        if ($ipPrefix < 0 || $ipPrefix > 32) {
            throw new IpException('invalid IPv4 prefix');
        }

        return new self(self::fromAddress($ipAddress), $ipPrefix, self::IP_4);
    }

    private static function fromIpSix(string $ipAddress, ?int $ipPrefix = null): self
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new IpException('invalid IPv6 address');
        }
        if (null === $ipPrefix) {
            $ipPrefix = 128;
        }
        if ($ipPrefix < 0 || $ipPrefix > 128) {
            throw new IpException('invalid IPv6 prefix');
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
