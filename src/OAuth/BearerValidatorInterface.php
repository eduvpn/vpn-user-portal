<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OAuth;

interface BearerValidatorInterface
{
    /**
     * @param string $authorizationHeader
     *
     * @return VpnAccessTokenInfo
     */
    public function validate($authorizationHeader);
}
