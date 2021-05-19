<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use InvalidArgumentException;
use LC\Portal\Exception\IPException;

class IP
{
    /** @var string */
    private $ipAddress;

    /** @var int */
    private $ipPrefix;

    /** @var int */
    private $ipFamily;

    public function __construct(string $ipAddressPrefix)
    {
        // detect if there is a prefix
        $hasPrefix = false !== mb_strpos($ipAddressPrefix, '/');
        if ($hasPrefix) {
            [$ipAddress, $ipPrefix] = explode('/', $ipAddressPrefix);
        } else {
            $ipAddress = $ipAddressPrefix;
            $ipPrefix = null;
        }

        // validate the IP address
        if (false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            throw new IPException('invalid IP address');
        }

        $is6 = false !== mb_strpos($ipAddress, ':');
        if ($is6) {
            if (null === $ipPrefix) {
                $ipPrefix = 128;
            }

            if (!is_numeric($ipPrefix) || 0 > $ipPrefix || 128 < $ipPrefix) {
                throw new IPException('IP prefix must be a number between 0 and 128');
            }
            // normalize the IPv6 address
            $ipAddress = inet_ntop(inet_pton($ipAddress));
        } else {
            if (null === $ipPrefix) {
                $ipPrefix = 32;
            }
            if (!is_numeric($ipPrefix) || 0 > $ipPrefix || 32 < $ipPrefix) {
                throw new IPException('IP prefix must be a number between 0 and 32');
            }
        }

        $this->ipAddress = $ipAddress;
        $this->ipPrefix = (int) $ipPrefix;
        $this->ipFamily = $is6 ? 6 : 4;
    }

    public function __toString(): string
    {
        return $this->getAddressPrefix();
    }

    public function getAddress(): string
    {
        return $this->ipAddress;
    }

    public function getPrefix(): int
    {
        return $this->ipPrefix;
    }

    public function getAddressPrefix(): string
    {
        return sprintf('%s/%d', $this->getAddress(), $this->getPrefix());
    }

    public function getFamily(): int
    {
        return $this->ipFamily;
    }

    /**
     * IPv4 only.
     */
    public function getNetmask(): string
    {
        $this->requireIPv4();

        return long2ip(-1 << (32 - $this->getPrefix()));
    }

    /**
     * IPv4 only.
     */
    public function getNetwork(): string
    {
        $this->requireIPv4();

        return long2ip(ip2long($this->getAddress()) & ip2long($this->getNetmask()));
    }

    /**
     * IPv4 only.
     */
    public function getNumberOfHosts(): int
    {
        $this->requireIPv4();

        return (int) 2 ** (32 - $this->getPrefix()) - 2;
    }

    /**
     * @return array<IP>
     */
    public function split(int $networkCount): array
    {
        if (0 !== ($networkCount & ($networkCount - 1))) {
            throw new InvalidArgumentException('parameter must be power of 2');
        }

        if (4 === $this->getFamily()) {
            return $this->split4($networkCount);
        }

        return $this->split6($networkCount);
    }

    public function getFirstHost(): string
    {
        if (4 === $this->ipFamily && 31 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }
        if (6 === $this->ipFamily && 127 <= $this->ipPrefix) {
            throw new IPException('network not big enough');
        }

        $hexIp = bin2hex(inet_pton($this->ipAddress));
        $lastDigit = hexdec(substr($hexIp, -1));
        // XXX how does this even work?!
        // XXX it doesn't! it first has to clear all the bits from the prefix and then add 1
        $hexIp = substr_replace($hexIp, $lastDigit + 1, -1);

        return inet_ntop(hex2bin($hexIp));
    }

    /**
     * @return array<IP>
     */
    private function split4(int $networkCount): array
    {
        if (2 ** (32 - $this->getPrefix() - 2) < $networkCount) {
            throw new IPException('network too small to split in this many networks');
        }

        $prefix = $this->getPrefix() + log($networkCount, 2);
        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            $noHosts = 2 ** (32 - $prefix);
            $networkAddress = long2ip((int) ($i * $noHosts + ip2long($this->getNetwork())));
            $splitRanges[] = new self($networkAddress.'/'.$prefix);
        }

        return $splitRanges;
    }

    /**
     * @return array<IP>
     */
    private function split6(int $networkCount): array
    {
        // we will ALWAYS assign a /112 to every OpenVPN process. So we need to
        // figure out how big our network needs to be *AT LEAST* given the
        // number of networks to split in...
        $requiredBits = (int) log($networkCount, 2);
        $minPrefix = 112 - $requiredBits;
        if ($minPrefix < $this->getPrefix()) {
            throw new IPException('network too small, must be >= /'.$minPrefix);
        }

        // we make math much easier for us like this!
        if (0 !== $this->getPrefix() % 4) {
            throw new IPException('network prefix length must be divisible by 4');
        }

        $hexAddress = bin2hex(inet_pton($this->getAddress()));
        // set the prefix bytes to 0, e.g. for a /64 clear the last 64 bits
        $clearPrefixLength = (int) (32 - ($this->getPrefix() / 4));
        $hexAddress = substr($hexAddress, 0, -$clearPrefixLength).str_repeat('0', $clearPrefixLength);

        // determine the addresses with the /112 prefix
        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            // the last 4 bytes are always 0 for the /112
            $hexAddress = substr($hexAddress, 0, -(\strlen(dechex($i)) + 4)).dechex($i).'0000';
            $splitRanges[] = new self(
                sprintf('%s/112', inet_ntop(hex2bin($hexAddress)))
            );
        }

        return $splitRanges;
    }

    private function requireIPv4(): void
    {
        if (4 !== $this->getFamily()) {
            throw new IPException('method only for IPv4');
        }
    }
}
