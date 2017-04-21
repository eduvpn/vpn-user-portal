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

use DateTime;
use fkooman\OAuth\Client\OAuth2Client;
use fkooman\OAuth\Client\Provider;
use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
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

    /** @var \SURFnet\VPN\Common\Http\SessionInterface */
    private $session;

    public function setUp()
    {
        $random = $this->getMockBuilder('\fkooman\OAuth\Client\RandomInterface')->getMock();
        $random->method('get')->willReturn('state12345abcde');

        $httpClient = new TestHttpClient();

        $serverClient = new ServerClient($httpClient, 'serverClient');

        $this->session = new TestSession();
        $this->service = new Service();
        $this->service->addModule(
            new VootModule(
                new OAuth2Client(
                    new Provider('client_id', 'client_secret', 'https://example.org/authorize', 'https://example.org/token'),
                    new VootTokenStorage($serverClient),
                    new TestOAuthHttpClient(),
                    $random,
                    new NullLogger(),
                    new DateTime()
                ),
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
        $this->assertSame('https://example.org/authorize?client_id=client_id&redirect_uri=http%3A%2F%2Fvpn.example%2F_voot%2Fcallback&scope=groups&state=state12345abcde&response_type=code', $response->getHeader('Location'));

        $this->assertSame('https://example.org/authorize?client_id=client_id&redirect_uri=http%3A%2F%2Fvpn.example%2F_voot%2Fcallback&scope=groups&state=state12345abcde&response_type=code', $this->session->get('_voot_state'));
        $this->assertSame('http://vpn.example/foo', $this->session->get('_voot_return_to'));
    }

    public function testCallback()
    {
        $this->session->set('_voot_state', 'https://example.org/authorize?client_id=client_id&redirect_uri=http%3A%2F%2Fvpn.example%2F_voot%2Fcallback&scope=groups&state=state12345abcde&response_type=code');
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
