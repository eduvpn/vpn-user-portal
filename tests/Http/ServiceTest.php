<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests\Http;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\Service;

/**
 * @internal
 * @coversNothing
 */
final class ServiceTest extends TestCase
{
    public function testGet(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/foo',
            ]
        );

        $service = new Service();
        $service->get(
            '/foo',
        /**
         * @return \Vpn\Portal\Http\Response
         */
        function (Request $request) {
            $response = new Response(201, 'application/json');
            $response->setBody('{}');

            return $response;
        }
        );
        $service->post(
            '/bar',
        // @return \Vpn\Portal\Http\Response
        fn (Request $request) => new Response()
        );
        $response = $service->run($request);

        static::assertSame(201, $response->getStatusCode());
        static::assertSame('{}', $response->getBody());
    }

    public function testMissingDocument(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/bar',
            ]
        );

        $service = new Service();
        $service->get(
            '/foo',
        /**
         * @return \Vpn\Portal\Http\Response
         */
        function (Request $request) {
            $response = new Response(201, 'application/json');
            $response->setBody('{}');

            return $response;
        }
        );
        $response = $service->run($request);

        static::assertSame(404, $response->getStatusCode());
        static::assertSame('{"error":"\"/bar\" not found"}', $response->getBody());
    }

    public function testUnsupportedMethod(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'DELETE',
                'REQUEST_URI' => '/foo',
            ]
        );

        $service = new Service();
        $service->get(
            '/foo',
            // @return \Vpn\Portal\Http\Response
            fn (Request $request) => new Response()
        );
        $service->post(
            '/foo',
            // @return \Vpn\Portal\Http\Response
            fn (Request $request) => new Response()
        );
        $response = $service->run($request);
        static::assertSame(405, $response->getStatusCode());
        static::assertSame('GET,POST', $response->getHeader('Allow'));
        static::assertSame('{"error":"method \"DELETE\" not allowed"}', $response->getBody());
    }

    public function testUnsupportedMethodMissingDocument(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_METHOD' => 'DELETE',
                'REQUEST_URI' => '/bar',
            ]
        );

        $service = new Service();
        $service->get(
            '/foo',
            // @return \Vpn\Portal\Http\Response
            fn (Request $request) => new Response()
        );
        $service->post(
            '/foo',
            // @return \Vpn\Portal\Http\Response
            fn (Request $request) => new Response()
        );
        $response = $service->run($request);
        static::assertSame(404, $response->getStatusCode());
        static::assertSame('{"error":"\"/bar\" not found"}', $response->getBody());
    }

    public function testHooks(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/foo',
            ]
        );

        $service = new Service();
        $callbackHook = new CallbackHook(
            // @return string
            fn (Request $request) => '12345'
        );
        $service->addBeforeHook('test', $callbackHook);

        $service->get(
            '/foo',
        /**
         * @return \Vpn\Portal\Http\Response
         */
        function (Request $request, array $hookData) {
            $response = new Response();
            $response->setBody($hookData['test']);

            return $response;
        }
        );
        $response = $service->run($request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('12345', $response->getBody());
    }

    public function testHookResponse(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/foo',
            ]
        );
        $service = new Service();
        $callbackHook = new CallbackHook(
            // @return \Vpn\Portal\Http\Response
            fn (Request $request) => new Response(201)
        );
        $service->addBeforeHook('test', $callbackHook);

        $service->get(
            '/foo',
        // @return \Vpn\Portal\Http\Response
        fn (Request $request, array $hookData) => new Response()
        );
        $response = $service->run($request);

        static::assertSame(201, $response->getStatusCode());
    }

    public function testHookDataPassing(): void
    {
        $request = new TestRequest([]);
        $service = new Service();
        $service->addBeforeHook(
            'test',
            new CallbackHook(
                /**
                 * @return string
                 */
                function (Request $request, array $hookData) {
                    // this should be available in the next before hook
                    return '12345';
                }
            )
        );
        $service->addBeforeHook(
            'test2',
            new CallbackHook(
                /**
                 * @return \Vpn\Portal\Http\Response
                 */
                function (Request $request, array $hookData) {
                    $response = new Response();
                    $response->setBody($hookData['test']);

                    return $response;
                }
            )
        );
        $response = $service->run($request);
        static::assertSame('12345', $response->getBody());
    }

    public function testBrowserNotFoundWithoutTpl(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/bar',
                'HTTP_ACCEPT' => 'text/html',
            ]
        );

        $service = new Service();
        $service->get(
            '/foo',
        // @return \Vpn\Portal\Http\Response
        fn (Request $request) => new Response(200)
        );
        $response = $service->run($request);
        static::assertSame(404, $response->getStatusCode());
        static::assertSame('404: "/bar" not found', $response->getBody());
    }

    public function testBrowserNotFoundWithTpl(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/bar',
                'HTTP_ACCEPT' => 'text/html',
            ]
        );

        $service = new Service(new TestHtmlTpl());
        $service->get(
            '/foo',
        // @return \Vpn\Portal\Http\Response
        fn (Request $request) => new Response(200)
        );
        $response = $service->run($request);
        static::assertSame(404, $response->getStatusCode());
        static::assertSame('<html><head><title>404</title></head><body><h1>Error (404)</h1><p>"/bar" not found</p></body></html>', $response->getBody());
    }
}
