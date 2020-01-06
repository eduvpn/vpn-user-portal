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
use LC\Common\Http\RadiusAuth;
use LC\Common\Http\SessionInterface;
use LC\Common\TplInterface;
use Psr\Log\LoggerInterface;

class FormRadiusAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl, LoggerInterface $logger)
    {
        $serverList = $config->getItem('serverList');
        $userAuth = new RadiusAuth($logger, $serverList);
        if (null !== $addRealm = $config->optionalItem('addRealm')) {
            $userAuth->setRealm($addRealm);
        }
        if (null !== $nasIdentifier = $config->optionalItem('nasIdentifier')) {
            $userAuth->setNasIdentifier($nasIdentifier);
        }

        parent::__construct($userAuth, $session, $tpl);
    }
}
