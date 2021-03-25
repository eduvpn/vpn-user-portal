<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Exception;

use Exception;

class HttpException extends Exception
{
    /** @var array */
    private $responseHeaders;

    /**
     * @param string $message
     * @param int    $code
     */
    public function __construct($message, $code, array $responseHeaders = [], Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }
}
