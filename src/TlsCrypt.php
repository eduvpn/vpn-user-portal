<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use ParagonIE\ConstantTime\Hex;

class TlsCrypt
{
    /** @var string */
    private $dataDir;

    /**
     * @param string $dataDir
     */
    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @return void
     */
    public function init()
    {
        $tlsCryptFile = sprintf('%s/tls-crypt.key', $this->dataDir);

        // generate the tls-crypt file if it does not exist
        if (false === FileIO::exists($tlsCryptFile)) {
            FileIO::writeFile($tlsCryptFile, self::generateTlsCrypt());
        }
    }

    /**
     * @return string
     */
    public function get()
    {
        $tlsCryptFile = sprintf('%s/tls-crypt.key', $this->dataDir);

        return FileIO::readFile($tlsCryptFile);
    }

    /**
     * @return string
     */
    private static function generateTlsCrypt()
    {
        // Same as $(openvpn --genkey --secret <file>)
        $randomData = wordwrap(Hex::encode(random_bytes(256)), 32, "\n", true);

        return <<< EOF
#
# 2048 bit OpenVPN static key
#
-----BEGIN OpenVPN Static key V1-----
$randomData
-----END OpenVPN Static key V1-----

EOF;
    }
}
