<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Config;
use LC\Portal\Http\AuthModuleInterface;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\UserInfo;
use LC\Portal\Http\UserInfoInterface;

class MellonAuthModule implements AuthModuleInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function userInfo(Request $request): ?UserInfoInterface
    {
        $userIdAttribute = $this->config->requireString('userIdAttribute');
        $nameIdSerialization = $this->config->requireBool('nameIdSerialization', false);
        $permissionAttribute = $this->config->optionalString('permissionAttribute');

        $userId = trim(strip_tags($request->requireHeader($userIdAttribute)));

        if ($nameIdSerialization) {
            if (\in_array($userIdAttribute, ['MELLON_NAME_ID', 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10'], true)) {
                // only for NAME_ID and eduPersonTargetedID, serialize it the way Shibboleth does
                // it by prefixing it with the IdP entityID and SP entityID
                $idpEntityId = $request->requireHeader('MELLON_IDP');
                $spEntityId = $this->config->requireString('spEntityId');
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

    public function startAuth(Request $request): ?Response
    {
        return null;
    }
}
