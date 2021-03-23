<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\CredentialValidatorInterface;
use LC\Common\Http\FormAuthentication;
use LC\Common\Http\Service;
use LC\Common\Http\SessionInterface;
use LC\Common\Http\UserInfo;
use LC\Common\TplInterface;

class FormPdoAuthentication extends FormAuthentication implements CredentialValidatorInterface
{
    /** @var \LC\Portal\Storage */
    private $storage;

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
