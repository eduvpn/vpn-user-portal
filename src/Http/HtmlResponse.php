<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class HtmlResponse extends Response
{
    /**
     * @param string $responsePage
     * @param int    $responseCode
     */
    public function __construct($responsePage, $responseCode = 200)
    {
        parent::__construct($responseCode, 'text/html');
        $this->setBody($responsePage);
    }
}
