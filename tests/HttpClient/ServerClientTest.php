<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\HttpClient;

use LC\Portal\HttpClient\Exception\HttpClientException;
use LC\Portal\HttpClient\ServerClient;
use PHPUnit\Framework\TestCase;

class ServerClientTest extends TestCase
{
    public function testGet(): void
    {
        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $this->assertTrue($serverClient->get('foo'));
    }

    public function testQueryParameter(): void
    {
        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $this->assertTrue($serverClient->get('foo', ['foo' => 'bar']));
    }

    public function testError(): void
    {
        try {
            $httpClient = new TestHttpClient();
            $serverClient = new ServerClient($httpClient, 'serverClient');
            $serverClient->get('error');
            self::fail();
        } catch (HttpClientException $e) {
            self::assertSame('[400] GET "serverClient/error": errorValue', $e->getMessage());
        }
    }

    public function testPost(): void
    {
        $httpClient = new TestHttpClient();
        $serverClient = new ServerClient($httpClient, 'serverClient');
        $this->assertTrue($serverClient->post('foo', ['foo' => 'bar']));
    }
}
