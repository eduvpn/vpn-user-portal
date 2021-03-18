<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\FormAuthentication;
use LC\Common\Http\Service;
use LC\Common\Http\SessionInterface;
use LC\Common\TplInterface;

class FormPdoAuthentication extends FormAuthentication
{
    /** @var \LC\Portal\Storage */
    private $storage;

    public function __construct(SessionInterface $session, TplInterface $tpl, Storage $storage)
    {
        parent::__construct($storage, $session, $tpl);
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        parent::init($service);

        // add module for changing password
        $service->addModule(
            new PasswdModule(
                $this->tpl,
                $this->storage
            )
        );
    }
}
