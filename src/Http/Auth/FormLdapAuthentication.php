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
use LC\Portal\Http\SessionInterface;
use LC\Portal\LogInterface;
use LC\Portal\TplInterface;

class FormLdapAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl, LogInterface $logger)
    {
        $ldapClient = new LdapClient(
            $config->requireString('ldapUri')
        );

        // XXX fix documentation for type permissionAttribute, can only be array<string> now!
        $permissionAttribute = $config->requireArray('permissionAttribute', []);

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
