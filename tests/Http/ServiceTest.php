<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function testGet(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/foo',
            ]
        );

        $service = new Service();
        $service->get('/foo',
        /**
         * @return \LC\Portal\Http\Response
         */
        function (Request $request) {
            $response = new Response(201, 'application/json');
            $response->setBody('{}');

            return $response;
        });
        $service->post('/bar',
        /*
         * @return \LC\Portal\Http\Response
         */
        fn (Request $request) => new Response());
        $response = $service->run($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{}', $response->getBody());
    }

    public function testMissingDocument(): void
    {
        $request = new TestRequest(
            [
                'REQUEST_URI' => '/bar',
            ]
        );

        $service = new Service();
        $service->get('/foo',
        /**
         * @return \LC\Portal\Http\Response
         */
        function (Request $request) {
            $response = new Response(201, 'application/json');
            $response->setBody('{}');

            return $response;
        });
        $response = $service->run($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"\"/bar\" not found"}', $response->getBody());
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
        $service->get('/foo',
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new Response()
        );
        $service->post('/foo',
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new Response()
        );
        $response = $service->run($request);
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET,POST', $response->getHeader('Allow'));
        $this->assertSame('{"error":"method \"DELETE\" not allowed"}', $response->getBody());
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
        $service->get('/foo',
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new Response()
        );
        $service->post('/foo',
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new Response()
        );
        $response = $service->run($request);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"\"/bar\" not found"}', $response->getBody());
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
            /*
             * @return string
             */
            fn (Request $request) => '12345'
        );
        $service->addBeforeHook('test', $callbackHook);

        $service->get('/foo',
        /**
         * @return \LC\Portal\Http\Response
         */
        function (Request $request, array $hookData) {
            $response = new Response();
            $response->setBody($hookData['test']);

            return $response;
        });
        $response = $service->run($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('12345', $response->getBody());
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
            /*
             * @return \LC\Portal\Http\Response
             */
            fn (Request $request) => new Response(201)
        );
        $service->addBeforeHook('test', $callbackHook);

        $service->get('/foo',
        /*
         * @return \LC\Portal\Http\Response
         */
        fn (Request $request, array $hookData) => new Response());
        $response = $service->run($request);

        $this->assertSame(201, $response->getStatusCode());
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
                 * @return \LC\Portal\Http\Response
                 */
                function (Request $request, array $hookData) {
                    $response = new Response();
                    $response->setBody($hookData['test']);

                    return $response;
                }
            )
        );
        $response = $service->run($request);
        $this->assertSame('12345', $response->getBody());
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
        $service->get('/foo',
        /*
         * @return \LC\Portal\Http\Response
         */
        fn (Request $request) => new Response(200));
        $response = $service->run($request);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('404: "/bar" not found', $response->getBody());
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
        $service->get('/foo',
        /*
         * @return \LC\Portal\Http\Response
         */
        fn (Request $request) => new Response(200));
        $response = $service->run($request);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('<html><head><title>404</title></head><body><h1>Error (404)</h1><p>"/bar" not found</p></body></html>', $response->getBody());
    }
}
