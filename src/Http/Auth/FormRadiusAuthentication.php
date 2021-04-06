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
use LC\Portal\Http\RadiusAuth;
use LC\Portal\Http\SessionInterface;
use LC\Portal\TplInterface;
use Psr\Log\LoggerInterface;

class FormRadiusAuthentication extends FormAuthentication
{
    public function __construct(Config $config, SessionInterface $session, TplInterface $tpl, LoggerInterface $logger)
    {
        $serverList = $config->requireArray('serverList');
        $userAuth = new RadiusAuth($logger, $serverList);
        if (null !== $addRealm = $config->optionalString('addRealm')) {
            $userAuth->setRealm($addRealm);
        }
        if (null !== $nasIdentifier = $config->optionalString('nasIdentifier')) {
            $userAuth->setNasIdentifier($nasIdentifier);
        }

        parent::__construct($userAuth, $session, $tpl);
    }
}
