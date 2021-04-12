<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\Portal\Http\AbstractHook;
use LC\Portal\Http\Request;

class CallbackHook extends AbstractHook
{
    /** @var callable */
    private $before;

    public function __construct(callable $before)
    {
        $this->before = $before;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        return \call_user_func($this->before, $request, $hookData);
    }
}
