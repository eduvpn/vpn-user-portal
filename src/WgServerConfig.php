<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\WireGuard\WgKeyPool;

class WgServerConfig
{
    private WgKeyPool $wgKeyPool;

    public function __construct(WgKeyPool $wgKeyPool)
    {
        $this->wgKeyPool = $wgKeyPool;
    }

    /**
     * @return array<string,string>
     */
    public function getProfile(ProfileConfig $profileConfig): array
    {
        $wgDevice = 'wg'.(string) ($profileConfig->profileNumber() - 1);
        $listenPort = 51820 + $profileConfig->profileNumber() - 1;
        $ipFour = new IP($profileConfig->range());  // XXX use getNetwork
        $ipSix = new IP($profileConfig->range6());  // XXX use getNetwork otherwise if the range6 is not .0 or :: it will just increment
        $firstHostFour = $ipFour->getFirstHost().'/'.$ipFour->getPrefix();
        $firstHostSix = $ipSix->getFirstHost().'/'.$ipSix->getPrefix();
        $privateKey = $this->wgKeyPool->get($profileConfig->profileId());

        // XXX make sure the prefix is there on Address

        $wgConfig = <<< EOF
            [Interface]
            Address = $firstHostFour,$firstHostSix
            ListenPort = $listenPort
            PrivateKey = $privateKey
            EOF;

        return [$wgDevice.'.conf' => $wgConfig];
    }
}
