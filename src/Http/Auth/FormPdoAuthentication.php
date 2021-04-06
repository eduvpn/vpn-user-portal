<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Http\CredentialValidatorInterface;
use LC\Portal\Http\PasswdModule;
use LC\Portal\Http\Service;
use LC\Portal\Http\SessionInterface;
use LC\Portal\Http\SessionInterface;
use LC\Portal\Http\UserInfo;
use LC\Portal\PasswdModule;
use LC\Portal\Storage;
use LC\Portal\TplInterface;

class FormPdoAuthentication extends FormAuthentication implements CredentialValidatorInterface
{
    private Storage $storage;

    public function __construct(SessionInterface $session, TplInterface $tpl, Storage $storage)
    {
        parent::__construct($this, $session, $tpl);
        $this->storage = $storage;
    }

    public function init(Service $service): void
    {
        parent::init($service);

        // add module for changing password
        $service->addModule(
            new PasswdModule(
                $this,
                $this->tpl,
                $this->storage
            )
        );
    }

    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        if (null === $passwordHash = $this->storage->getPasswordHash($authUser)) {
            // no such user
            return false;
        }

        if (password_verify($authPass, $passwordHash)) {
            return new UserInfo($authUser, []);
        }

        return false;
    }
}
