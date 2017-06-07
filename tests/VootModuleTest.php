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

namespace SURFnet\VPN\Portal\Tests;

use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Http\NullAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Portal\VootModule;
use SURFnet\VPN\Portal\VootTokenStorage;

class VootModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \fkooman\OAuth\Client\SessionInterface */
    private $oauthSession;

    public function setUp()
    {
        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $this->session = new TestSession();
        $this->service = new Service();
        $this->oauthSession = new TestOAuthSession();

        $client = new OAuthClient(
            new VootTokenStorage($serverClient),
            new TestOAuthHttpClient(),
            $this->oauthSession,
            new TestOAuthClientRandom()
        );
        $client->setProvider(new Provider('client_id', 'client_secret', 'https://example.org/authorize', 'https://example.org/token'));
        $this->service->addModule(
            new VootModule(
                $client,
                $serverClient,
                $this->session
            )
        );
        $this->service->addBeforeHook('auth', new NullAuthenticationHook('foo'));
    }

    public function testNewGet()
    {
        // redirects to OAuth provider
        $response = $this->makeRequest('GET', '/_voot/authorize', ['return_to' => 'http://vpn.example/foo'], [], true);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://example.org/authorize?client_id=client_id&redirect_uri=http%3A%2F%2Fvpn.example%2F_voot%2Fcallback&scope=groups&state=random_1&response_type=code&code_challenge_method=S256&code_challenge=elRpCEYh8XiYBhjcG1EBHe5qHscwyYvQC-xtVeca5jM', $response->getHeader('Location'));
        $this->assertSame('http://vpn.example/foo', $this->session->get('_voot_return_to'));
    }

    public function testCallback()
    {
        $this->oauthSession->set('_oauth2_session', ['user_id' => 'foo', 'provider_id' => 'https://example.org/authorize|client_id', 'client_id' => 'client_id', 'redirect_uri' => 'http%3A%2F%2Fvpn.example%2F_voot%2Fcallback', 'scope' => 'groups', 'state' => 'state12345abcde', 'response_type' => 'code', 'code_verifier' => 'ABCD']);
        $this->session->set('_voot_return_to', 'http://vpn.example/foo');

        $response = $this->makeRequest(
            'GET',
            '/_voot/callback',
            [
                'state' => 'state12345abcde',
                'code' => '12345',
            ],
            [],
            true
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://vpn.example/foo', $response->getHeader('Location'));
    }

    private function makeRequest($requestMethod, $pathInfo, array $getData = [], array $postData = [], $returnResponseObj = false)
    {
        $response = $this->service->run(
            new Request(
                [
                    'SERVER_PORT' => 80,
                    'SERVER_NAME' => 'vpn.example',
                    'REQUEST_METHOD' => $requestMethod,
                    'REQUEST_URI' => $pathInfo,
                    'SCRIPT_NAME' => '/index.php',
                ],
                $getData,
                $postData
            )
        );

        if ($returnResponseObj) {
            return $response;
        }

        $responseBody = $response->getBody();

        return json_decode($responseBody, true);
    }
}
