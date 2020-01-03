<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class MellonAuthentication implements BeforeHookInterface
{
    /** @var \LC\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        /** @var string */
        $userIdAttribute = $this->config->getItem('userIdAttribute');
        /** @var bool|null */
        $nameIdSerialization = $this->config->optionalItem('nameIdSerialization');
        /** @var string|null */
        $permissionAttribute = $this->config->optionalItem('permissionAttribute');

        $userId = trim(strip_tags($request->requireHeader($userIdAttribute)));
        if (null !== $nameIdSerialization && true === $nameIdSerialization) {
            if (\in_array($userIdAttribute, ['MELLON_NAME_ID', 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10'], true)) {
                // only for NAME_ID and eduPersonTargetedID, serialize it the way Shibboleth does
                // it by prefixing it with the IdP entityID and SP entityID
                $idpEntityId = $request->requireHeader('MELLON_IDP');
                /** @var string */
                $spEntityId = $this->config->getItem('spEntityId');
                $userId = sprintf('%s!%s!%s', $idpEntityId, $spEntityId, $userId);
            }
        }

        $userPermissions = [];
        if (null !== $permissionAttribute) {
            $permissionHeaderValue = $request->optionalHeader($permissionAttribute);
            if (null !== $permissionHeaderValue) {
                $userPermissions = explode(';', $permissionHeaderValue);
            }
        }

        return new UserInfo(
            $userId,
            $userPermissions
        );
    }
}
