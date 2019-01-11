<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\TplInterface;

/**
 * Augments the "template" with information about whether or not the user is
 * an "admin", i.e. should see the admin menu items.
 */
class AdminHook implements BeforeHookInterface
{
    /** @var array<string> */
    private $requiredEntitlementList;

    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /**
     * @param array<string>                    $requiredEntitlementList
     * @param \SURFnet\VPN\Common\TplInterface $tpl
     */
    public function __construct(array $requiredEntitlementList, TplInterface &$tpl)
    {
        $this->requiredEntitlementList = $requiredEntitlementList;
        $this->tpl = $tpl;
    }

    /**
     * @param Request $request
     * @param array   $hookData
     *
     * @return bool
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $whiteList = [
            'POST' => [
                '/_saml/acs',
                '/_form/auth/verify',
                '/_form/auth/logout',   // DEPRECATED
                '/_logout',
            ],
            'GET' => [
                '/_saml/logout',
                '/_saml/login',
                '/_saml/metadata',
            ],
        ];
        if (Service::isWhitelisted($request, $whiteList)) {
            return false;
        }

        if (!array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }

        $userInfo = $hookData['auth'];
        $userEntitlementList = $userInfo->entitlementList();
        foreach ($userEntitlementList as $userEntitlement) {
            if (\in_array($userEntitlement, $this->requiredEntitlementList, true)) {
                $this->tpl->addDefault(['isAdmin' => true]);

                return true;
            }
        }

        $this->tpl->addDefault(['isAdmin' => false]);

        return false;
    }
}
