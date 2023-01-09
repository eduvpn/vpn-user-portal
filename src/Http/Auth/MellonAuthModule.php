<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Cfg\MellonAuthConfig;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\UserInfo;

class MellonAuthModule extends AbstractAuthModule
{
    private MellonAuthConfig $config;

    public function __construct(MellonAuthConfig $config)
    {
        $this->config = $config;
    }

    public function userInfo(Request $request): ?UserInfo
    {
        $userIdAttribute = $this->config->userIdAttribute();
        $nameIdSerialization = $this->config->nameIdSerialization();
        $permissionAttributeList = $this->config->permissionAttributeList();

        $userId = trim(strip_tags($request->requireHeader($userIdAttribute)));

        if ($nameIdSerialization) {
            if (\in_array($userIdAttribute, ['MELLON_NAME_ID', 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10'], true)) {
                // only for NAME_ID and eduPersonTargetedID, serialize it the way Shibboleth does
                // it by prefixing it with the IdP entityID and SP entityID
                $idpEntityId = $request->requireHeader('MELLON_IDP');
                $spEntityId = $this->config->spEntityId();
                $userId = sprintf('%s!%s!%s', $idpEntityId, $spEntityId, $userId);
            }
        }

        $permissionList = [];
        foreach ($permissionAttributeList as $permissionAttribute) {
            if (null !== $permissionAttributeValue = $request->optionalHeader($permissionAttribute)) {
                $permissionList = array_merge($permissionList, explode(';', $permissionAttributeValue));
            }
        }

        return new UserInfo(
            $userId,
            $permissionList
        );
    }

    public function triggerLogout(Request $request): Response
    {
        return new RedirectResponse(
            $request->getScheme().'://'.$request->getAuthority().'/saml/logout?'.http_build_query(['ReturnTo' => $request->requireReferrer()])
        );
    }
}
