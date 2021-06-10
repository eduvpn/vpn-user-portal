<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class WgServerConfig
{
    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
     *
     * @return array<string,string>
     */
    public function get(array $profileConfigList): array
    {
        $privateKey = $this->privateKey();
        $ipFourList = [];
        $ipSixList = [];
        foreach ($profileConfigList as $profileConfig) {
            $ipFour = new IP($profileConfig->range());  // XXX use getNetwork
            $ipSix = new IP($profileConfig->range6());  // XXX use getNetwork otherwise if the range6 is not .0 or :: it will just increment
            $ipFourList[] = $ipFour->getFirstHost().'/'.$ipFour->getPrefix();
            $ipSixList[] = $ipSix->getFirstHost().'/'.$ipSix->getPrefix();
        }
        $ipList = implode(',', array_merge($ipFourList, $ipSixList));

        $wgConfig = <<< EOF
            [Interface]
            Address = $ipList
            ListenPort = 51820
            PrivateKey = $privateKey
            EOF;

        return ['wg0.conf' => $wgConfig];
    }

    private function privateKey(): string
    {
        $keyFile = $this->dataDir.'/wireguard.key';
        if (FileIO::exists($keyFile)) {
            return FileIO::readFile($keyFile);
        }

        $privateKey = self::generatePrivateKey();
        FileIO::writeFile($keyFile, $privateKey);

        return $privateKey;
    }

    /**
     * XXX duplicate in Wg.php.
     */
    private static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }
}
