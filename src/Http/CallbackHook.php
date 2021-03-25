<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class CallbackHook implements BeforeHookInterface, AfterHookInterface
{
    /** @var callable|null */
    private $before;

    /** @var callable|null */
    private $after;

    public function __construct(callable $before = null, callable $after = null)
    {
        $this->before = $before;
        $this->after = $after;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        if (null !== $this->before) {
            return \call_user_func($this->before, $request, $hookData);
        }

        return null;
    }

    public function executeAfter(Request $request, Response $response)
    {
        if (null !== $this->after) {
            return \call_user_func($this->after, $request, $response);
        }

        return $response;
    }
}
