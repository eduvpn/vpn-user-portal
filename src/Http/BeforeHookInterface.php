<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

interface BeforeHookInterface
{
    /**
     * Execute a hook before routing.
     *
     * @param Request $request  the HTTP request
     * @param array   $hookData results from previously called hooks where the
     *                          key is the name given to the hook and the value contains the result
     *
     * @return mixed can return all types, if the result is a Response or a
     *               subclass of it, it is immediately returned to the client
     */
    public function executeBefore(Request $request, array $hookData);
}
