<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\OpenVpn\TlsCrypt;
use PDO;

class Init
{
    /** @var string */
    private $baseDir;

    /**
     * @param string $baseDir
     */
    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * @return void
     */
    public function init()
    {
        $dataDir = sprintf('%s/data', $this->baseDir);
        FileIO::createDir($dataDir, 0700);

        $easyRsaDir = sprintf('%s/easy-rsa', $this->baseDir);
        $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
        $dbFile = sprintf('%s/db.sqlite', $dataDir);
        $tlsCryptFile = sprintf('%s/tls-crypt.key', $dataDir);
        $oauthKeyFile = sprintf('%s/oauth.key', $dataDir);

        // CA
        if (!FileIO::exists($easyRsaDataDir)) {
            $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);
            $ca->init();
        }

        // DB
        $hasDbFile = FileIO::exists($dbFile);
        $storage = new Storage(
            new PDO(sprintf('sqlite://%s', $dbFile)),
            sprintf('%s/schema', $this->baseDir)
        );
        if (!$hasDbFile) {
            // we have to check *before* calling PDO if the file exists, as
            // just creating the PDO object already creates the database file...
            $storage->init();
        }
        $storage->update();

        // tls-crypt
        if (!FileIO::exists($tlsCryptFile)) {
            $tlsCrypt = TlsCrypt::generate();
            FileIO::writeFile($tlsCryptFile, $tlsCrypt->raw(), 0640);
        }

        // OAuth
        if (!FileIO::exists($oauthKeyFile)) {
            $secretKey = SecretKey::generate();
            FileIO::writeFile($oauthKeyFile, $secretKey->encode(), 0640);
        }
    }
}
