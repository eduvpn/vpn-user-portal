<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Exception;

use Exception;

class NodeApiException extends Exception
{
    /** @var string|null */
    private $userId;

    /**
     * @param string|null $userId
     * @param string      $message
     * @param int         $code
     */
    public function __construct($userId, $message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->userId = $userId;
    }

    /**
     * @return string|null
     */
    public function getUserId()
    {
        return $this->userId;
    }
}
