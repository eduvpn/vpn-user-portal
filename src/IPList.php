<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class IPList
{
    /** @var array<\LC\Portal\IP> */
    private array $ipList;

    /**
     * @param array<\LC\Portal\IP> $ipList
     */
    public function __construct(array $ipList = [])
    {
        $this->ipList = $ipList;
    }

    public function __toString(): string
    {
        return '['.implode(' ', $this->ipList).']';
    }

    public function add(IP $ip): void
    {
        // XXX check for duplicate
        // XXX check whether this range is already part of any of the existing
        // ones, then ignore it
        // XXX check whether any of the existing ones falls in this range and
        // replace it
        $this->ipList[] = $ip;
    }

    /**
     * Remove the specified prefix from the current IP list. Rewrites the
     * existing ranges to statisfy the removal.
     */
    public function remove(IP $i): void
    {
        foreach ($this->ipList as $k => $ip) {
            if ($i->equals($ip)) {
                unset($this->ipList[$k]);

                continue;
            }

            if ($ip->contains($i)) {
                unset($this->ipList[$k]);
                $this->ipList = array_merge($this->ipList, $ip->splitInHalf());
                sort($this->ipList);
                $this->remove($i);
            }
        }
    }

    /**
     * @return array<\LC\Portal\IP>
     */
    public function ls(): array
    {
        return $this->ipList;
    }
}
