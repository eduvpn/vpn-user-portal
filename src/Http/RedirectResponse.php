<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

class RedirectResponse extends Response
{
    /**
     * @param string $redirectUri
     * @param int    $statusCode
     */
    public function __construct($redirectUri, $statusCode = 302)
    {
        parent::__construct($statusCode);
        $this->addHeader('Location', $redirectUri);
    }
}
