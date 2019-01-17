<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use LetsConnect\Common\Http\HtmlResponse;
use LetsConnect\Common\Http\InputValidation;
use LetsConnect\Common\Http\PdoAuth;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\TplInterface;

class PasswdModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    /** @var \LetsConnect\Common\Http\PdoAuth */
    private $pdoAuth;

    public function __construct(TplInterface $tpl, PdoAuth $pdoAuth)
    {
        $this->tpl = $tpl;
        $this->pdoAuth = $pdoAuth;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/passwd',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalPasswd',
                        [
                            'userId' => $userInfo->id(),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/passwd',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                $userInfo = $hookData['auth'];

                $userPass = $request->getPostParameter('userPass');
                $newUserPass = InputValidation::userPass($request->getPostParameter('newUserPass'));
                $newUserPassConfirm = InputValidation::userPass($request->getPostParameter('newUserPassConfirm'));

                if (!$this->pdoAuth->isValid($userInfo->id(), $userPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->id(),
                                'errorCode' => 'wrongPassword',
                            ]
                        )
                    );
                }

                if ($newUserPass !== $newUserPassConfirm) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->id(),
                                'errorCode' => 'noMatchingPassword',
                            ]
                        )
                    );
                }

                if (!$this->pdoAuth->updatePassword($userInfo->id(), $newUserPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->id(),
                                'errorCode' => 'updateFailPassword',
                            ]
                        )
                    );
                }

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );
    }
}
