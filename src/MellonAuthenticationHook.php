<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\Request;
use LC\Common\Http\UserInfo;

class MellonAuthenticationHook implements BeforeHookInterface
{
    /** @var string */
    private $userIdAttribute;

    /** @var string|null */
    private $permissionAttribute;

    /** @var bool */
    private $nameIdSerialization;

    /** @var string|null */
    private $spEntityId;

    /**
     * @param string      $userIdAttribute
     * @param string|null $permissionAttribute
     * @param bool        $nameIdSerialization
     * @param string|null $spEntityId
     */
    public function __construct($userIdAttribute, $permissionAttribute, $nameIdSerialization, $spEntityId)
    {
        $this->userIdAttribute = $userIdAttribute;
        $this->permissionAttribute = $permissionAttribute;
        $this->nameIdSerialization = $nameIdSerialization;
        $this->spEntityId = $spEntityId;
    }

    /**
     * @param Request $request
     * @param array   $hookData
     *
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $userId = trim(strip_tags($request->requireHeader($this->userIdAttribute)));
        if ($this->nameIdSerialization) {
            if (\in_array($this->userIdAttribute, ['MELLON_NAME_ID', 'MELLON_urn:oid:1_3_6_1_4_1_5923_1_1_1_10'], true)) {
                // only for NAME_ID and eduPersonTargetedID, serialize it the way Shibboleth does
                // it by prefixing it with the IdP entityID and SP entityID
                $idpEntityId = $request->requireHeader('MELLON_IDP');
                if (null === $this->spEntityId) {
                    throw new HttpException('"spEntityId" MUST be set in configuration', 500);
                }
                $userId = sprintf('%s!%s!%s', $idpEntityId, $this->spEntityId, $userId);
            }
        }

        $userPermissions = [];
        if (null !== $this->permissionAttribute) {
            $permissionHeaderValue = $request->optionalHeader($this->permissionAttribute);
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
