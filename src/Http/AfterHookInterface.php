<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

interface AfterHookInterface
{
    /**
     * Execute a hook before returning the response to the client.
     *
     * @param Request  $request  the HTTP request
     * @param Response $response the HTTP response so far, the hook can modify
     *                           it
     *
     * @return Response the (modified) HTTP response
     */
    public function executeAfter(Request $request, Response $response);
}
