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
use LC\Common\Http\LdapAuth;
use LC\Common\Http\SessionInterface;
use LC\Common\LdapClient;
use LC\Common\TplInterface;
use Psr\Log\NullLogger;

class FormLdapAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl)
    {
        $ldapClient = new LdapClient(
            $config->getItem('ldapUri')
        );
        $userAuth = new LdapAuth(
            new NullLogger(),   // XXX fix logger
            $ldapClient,
            $config->getItem('bindDnTemplate'),
            $config->optionalItem('baseDn'),
            $config->optionalItem('userFilterTemplate'),
            $config->optionalItem('permissionAttribute')
        );

        parent::__construct($userAuth, $session, $tpl);
    }
}
