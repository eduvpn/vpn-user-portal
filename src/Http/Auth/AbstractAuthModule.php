<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Http\AuthModuleInterface;
use Vpn\Portal\Http\RedirectResponse;
use Vpn\Portal\Http\Request;
use Vpn\Portal\Http\Response;
use Vpn\Portal\Http\ServiceInterface;
use Vpn\Portal\Http\UserInfo;

abstract class AbstractAuthModule implements AuthModuleInterface
{
    public function init(ServiceInterface $service): void
    {
    }

    public function userInfo(Request $request): ?UserInfo
    {
        return null;
    }

    public function startAuth(Request $request): ?Response
    {
        return null;
    }

    public function triggerLogout(Request $request): Response
    {
        // by default we return to the place the users came from, it is up to
        // authentication mechanisms that implement their own logout, e.g.
        // SAML authentication to override this method
        return new RedirectResponse($request->requireReferrer());
    }

    /**
     * @param array<string,array<string>> $attributeNameValueList
     * @param ?array<string> $attributeNameFilter
     *
     * @return array<string>
     */
    public static function flattenPermissionList(array $attributeNameValueList, ?array $attributeNameFilter = null, string $authPrefix = 'A'): array
    {
        $permissionList = [];
        foreach ($attributeNameValueList as $attributeName => $attributeValueList) {
            if (null !== $attributeNameFilter && !in_array($attributeName, $attributeNameFilter, true)) {
                continue;
            }

            $permissionList = array_merge(
                $permissionList,
                array_map(
                    fn (string $attributeValue): string => sprintf('%s!%s!%s', $authPrefix, $attributeName, $attributeValue),
                    $attributeValueList
                )
            );
        }

        return $permissionList;
    }
}
