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

    public function add(IP $i): void
    {
        // check whether any of the existing prefixes already contain the
        // prefix to be added...
        foreach ($this->ipList as $ip) {
            if ($ip->equals($i)) {
                return;
            }
            if ($ip->contains($i)) {
                return;
            }
        }

        // check whether any of the existing prefixes belong to the prefix to
        // be added, then remove those
        foreach ($this->ipList as $k => $ip) {
            if ($i->contains($ip)) {
                unset($this->ipList[$k]);
            }
        }
        $this->ipList[] = $i;

        sort($this->ipList);
    }

    /**
     * Remove the specified prefix from the current IP list. Rewrites the
     * existing ranges to satisfy the removal.
     */
    public function remove(IP $i): void
    {
        foreach ($this->ipList as $k => $ip) {
            if ($ip->family() !== $i->family()) {
                continue;
            }

            if ($i->equals($ip)) {
                unset($this->ipList[$k]);

                continue;
            }

            if ($ip->contains($i)) {
                unset($this->ipList[$k]);
                $this->ipList = array_merge($this->ipList, $ip->splitInHalf());
                $this->remove($i);
            }
        }

        sort($this->ipList);
    }

    /**
     * @return array<\LC\Portal\IP>
     */
    public function ls(): array
    {
        return $this->ipList;
    }
}
