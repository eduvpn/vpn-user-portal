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

use GuzzleHttp\Client;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\HttpClient\CaClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Portal\OAuth\BearerAuthenticationHook;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\HttpClient\GuzzleHttpClient;
use SURFnet\VPN\Portal\OAuth\TokenStorage;
use SURFnet\VPN\Portal\VpnApiModule;
use SURFnet\VPN\Common\Http\Response;

$logger = new Logger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);
    $instanceId = $request->getServerName();

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    $config = Config::fromFile(sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId));

    $service = new Service();

    if ($config->v('enableOAuth')) {
        $tokenStorage = new TokenStorage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
        $tokenStorage->init();

        $service->addBeforeHook(
            'auth',
            new BearerAuthenticationHook(
                $tokenStorage
            )
        );

        // vpn-ca-api
        $guzzleCaClient = new GuzzleHttpClient(
            new Client([
                'defaults' => [
                    'auth' => [
                        $config->v('apiProviders', 'vpn-ca-api', 'userName'),
                        $config->v('apiProviders', 'vpn-ca-api', 'userPass'),
                    ],
                ],
            ])
        );
        $caClient = new CaClient($guzzleCaClient, $config->v('apiProviders', 'vpn-ca-api', 'apiUri'));

        // vpn-server-api
        $guzzleServerClient = new GuzzleHttpClient(
            new Client([
                'defaults' => [
                    'auth' => [
                        $config->v('apiProviders', 'vpn-server-api', 'userName'),
                        $config->v('apiProviders', 'vpn-server-api', 'userPass'),
                    ],
                ],
            ])
        );
        $serverClient = new ServerClient($guzzleServerClient, $config->v('apiProviders', 'vpn-server-api', 'apiUri'));

        // api module
        $vpnApiModule = new VpnApiModule(
            $serverClient,
            $caClient
        );
        $service->addModule($vpnApiModule);
    }
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(json_encode(['error' => $e->getMessage()]));
    $response->send();
}
