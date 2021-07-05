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
    const IP_4 = 4;
    const IP_6 = 6;

    private string $ipAddress;
    private int $ipPrefix;

    public function __construct(string $ipAddressPrefix)
    {
        $this->ipAddress = self::extractAddress($ipAddressPrefix);
        $this->ipPrefix = self::extractPrefix($ipAddressPrefix);
    }

    public function __toString(): string
    {
        return $this->ipAddress.'/'.$this->ipPrefix;
    }

    public function getAddress(): string
    {
        return $this->ipAddress;
    }

    public function getPrefix(): int
    {
        return $this->ipPrefix;
    }

    public function getNetwork(): self
    {
        return new self(
            self::gmpToAddr(
                gmp_and(
                    self::addrToGmp($this->getAddress()),
                    gmp_and(
                        gmp_sub(gmp_pow(2, $this->getAddressBits()), 1),
                        gmp_sub(gmp_pow(2, $this->getAddressBits()), gmp_pow(2, $this->getAddressBits() - $this->getPrefix()))
                    )
                ),
                $this->getAddressBits(),
            ).'/'.$this->getPrefix()
        );
    }

    public function getNetmask(): string
    {
        return self::gmpToAddr(
            gmp_xor(
                gmp_sub(gmp_pow(2, $this->getAddressBits()), 1),
                gmp_sub(gmp_pow(2, $this->getAddressBits() - $this->getPrefix()), 1),
            ),
            $this->getAddressBits()
        );
    }

    /**
     * IPv4 only.
     */
    public function getNumberOfHosts(): int
    {
        $this->requireIPv4();

        return (int) 2 ** (32 - $this->getPrefix()) - 2;
    }

    public function getFirstHost(): self
    {
        if (self::IP_4 === $this->getFamily() && 31 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }
        if (self::IP_6 === $this->getFamily() && 127 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }

        return new self(
            self::gmpToAddr(
                gmp_add(
                    $this->addrToGmp($this->getNetwork()->getAddress()),
                    1
                ),
                $this->getAddressBits()
            ).'/'.
            $this->getPrefix()
        );
    }

    /**
     * @return array<IP>
     */
    public function split(int $networkCount): array
    {
        // XXX introduce "maxPrefix" parameter?
        if (2 ** ($this->getAddressBits() - $this->getPrefix() - 2) < $networkCount) {
            throw new IPException('network too small to split in this many networks');
        }

        $requiredBits = (int) log($networkCount, 2);
        $prefixBits = self::IP_4 === $this->getFamily() ? $this->getPrefix() + $requiredBits : 112;
        if (self::IP_6 === $this->getFamily()) {
            $minPrefix = 112 - $requiredBits;
            if ($minPrefix < $this->getPrefix()) {
                throw new IPException('network too small, must be >= /'.$minPrefix);
            }
        }
        $netIp = $this->getNetwork();
        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            $noOfHosts = gmp_pow(2, $this->getAddressBits() - $prefixBits);
            $netAddress = gmp_add(gmp_mul($i, $noOfHosts), self::addrToGmp($netIp->getAddress()));
            $splitRanges[] = new self(self::gmpToAddr($netAddress, $this->getAddressBits()).'/'.$prefixBits);
        }

        return $splitRanges;
    }

    public function getFamily(): int
    {
        return false === strpos($this->ipAddress, ':') ? self::IP_4 : self::IP_6;
    }

    private function getAddressBits(): int
    {
        return self::IP_4 === $this->getFamily() ? 32 : 128;
    }

    private static function gmpToAddr(GMP $addr, int $addrBits): string
    {
        return inet_ntop(
            hex2bin(
                str_pad(
                    gmp_strval(
                        $addr,
                        16
                    ),
                    (int) ($addrBits / 4),
                    '0',
                    \STR_PAD_LEFT
                )
            )
        );
    }

    private static function addrToGmp(string $addr): GMP
    {
        return gmp_init(bin2hex(inet_pton($addr)), 16);
    }

    private static function extractAddress(string $ipAddressPrefix): string
    {
        if (false === strpos($ipAddressPrefix, '/')) {
            // normalize
            return inet_ntop(inet_pton($ipAddressPrefix));
        }

        // prefix is part of the input
        [$ipAddress, ] = explode('/', $ipAddressPrefix, 2);
        if (false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            throw new IPException('invalid IP address');
        }

        // normalize
        return inet_ntop(inet_pton($ipAddress));
    }

    private static function extractPrefix(string $ipAddressPrefix): int
    {
        if (false === strpos($ipAddressPrefix, '/')) {
            // prefix is not part of the input
            if (false === strpos($ipAddressPrefix, ':')) {
                // IPv4
                return 32;
            }

            // IPv6
            return 128;
        }

        // prefix is part of the input
        [$ipAddress, $ipPrefix] = explode('/', $ipAddressPrefix, 2);
        if (!is_numeric($ipPrefix)) {
            throw new IPException('prefix must be numeric');
        }
        if ($ipPrefix < 0) {
            throw new IPException('prefix must be >= 0');
        }
        if (false === strpos($ipAddress, ':')) {
            // IPv4
            if ($ipPrefix > 32) {
                throw new IPException('prefix for IPv4 address must be <= 32');
            }

            return (int) $ipPrefix;
        }

        // IPv6
        if ($ipPrefix > 128) {
            throw new IPException('prefix for IPv6 address must be <= 128');
        }

        return (int) $ipPrefix;
    }

    private function requireIPv4(): void
    {
        if (self::IP_4 !== $this->getFamily()) {
            throw new IPException('method only for IPv4');
        }
    }
}
