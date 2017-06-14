<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Storage;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\BearerAuthenticationHook;
use SURFnet\VPN\Portal\ForeignKeyListFetcher;
use SURFnet\VPN\Portal\VpnApiModule;

$logger = new Logger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    if (!file_exists($dataDir)) {
        if (false === @mkdir($dataDir, 0700, true)) {
            throw new RuntimeException(sprintf('unable to create folder "%s"', $dataDir));
        }
    }
    $config = Config::fromFile(sprintf('%s/config/%s/config.php', dirname(__DIR__), $instanceId));

    $service = new Service();

    if ($config->hasSection('Api')) {
        $storage = new Storage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
        $storage->init();

        $bearerValidator = new BearerValidator(
            $storage,
            FileIO::readFile(sprintf('%s/OAuth.key', $dataDir))
        );

        $foreignKeys = [];
        if ($config->getSection('Api')->hasItem('foreignKeys')) {
            $foreignKeys = array_merge($foreignKeys, $config->getSection('Api')->getItem('foreignKeys'));
        }

        if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
            $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/%s/foreign_key_list.json', dirname(__DIR__), $instanceId));
            $foreignKeys = array_merge($foreignKeys, $foreignKeyListFetcher->extract());
        }
        $bearerValidator->setForeignKeys($foreignKeys);

        $service->addBeforeHook(
            'auth',
            new BearerAuthenticationHook(
                $bearerValidator
            )
        );

        $serverClient = new ServerClient(
            new CurlHttpClient([$config->getItem('apiUser'), $config->getItem('apiPass')]),
            $config->getItem('apiUri')
        );

        // api module
        $vpnApiModule = new VpnApiModule(
            $serverClient
        );
        if ($config->hasItem('addVpnProtoPorts')) {
            $vpnApiModule->setAddVpnProtoPorts($config->getItem('addVpnProtoPorts'));
        }

        $service->addModule($vpnApiModule);
    }
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
