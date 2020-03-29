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
use LC\Common\TplInterface;
use Psr\Log\LoggerInterface;

class FormLdapAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl, LoggerInterface $logger)
    {
        $ldapClient = new LdapClient(
            $config->getItem('ldapUri')
        );
        $userAuth = new LdapAuth(
            $logger,
            $ldapClient,
            $config->getItem('bindDnTemplate'),
            $config->optionalItem('baseDn'),
            $config->optionalItem('userFilterTemplate'),
            $config->optionalItem('userIdAttribute'),
            $config->optionalItem('permissionAttribute')
        );

        parent::__construct($userAuth, $session, $tpl);
    }
}
