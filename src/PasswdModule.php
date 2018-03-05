<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\PdoAuth;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\TplInterface;

class PasswdModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\Http\PdoAuth */
    private $pdoAuth;

    public function __construct(TplInterface $tpl, PdoAuth $pdoAuth)
    {
        $this->tpl = $tpl;
        $this->pdoAuth = $pdoAuth;
    }

    public function init(Service $service)
    {
        $service->get(
            '/passwd',
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
