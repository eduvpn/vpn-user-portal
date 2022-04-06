<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Config;
use LC\Common\Http\FormAuthentication;
use LC\Common\Http\SessionInterface;
use LC\Common\Log\LoggerInterface;
use LC\Common\TplInterface;

class FormLdapAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl, LoggerInterface $logger)
    {
        $ldapClient = new LdapClient(
            $config->requireString('ldapUri')
        );

        // convert permissionAttribute to (empty) array
        if (null === $permissionAttribute = $config->optionalItem('permissionAttribute')) {
            $permissionAttribute = [];
        }
        if (!\is_array($permissionAttribute)) {
            $permissionAttribute = [$permissionAttribute];
        }

        $userAuth = new LdapAuth(
            $logger,
            $ldapClient,
            $config->optionalString('bindDnTemplate'),
            $config->optionalString('baseDn'),
            $config->optionalString('userFilterTemplate'),
            $config->optionalString('userIdAttribute'),
            $config->optionalString('addRealm'),
            $permissionAttribute,
            $config->optionalString('searchBindDn'),
            $config->optionalString('searchBindPass')
        );

        parent::__construct($userAuth, $session, $tpl);
    }
}
