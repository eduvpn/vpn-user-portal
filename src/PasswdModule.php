<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\HtmlResponse;
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
                $userId = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalPasswd',
                        [
                            'userId' => $userId,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/passwd',
            function (Request $request, array $hookData) {
                $userId = $hookData['auth'];

                $userPass = $request->getPostParameter('userPass');
                $newUserPass = $request->getPostParameter('newUserPass');
                $newUserPassConfirm = $request->getPostParameter('newUserPassConfirm');

                if (!$this->pdoAuth->isValid($userId, $userPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userId,
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
                                'userId' => $userId,
                                'errorCode' => 'noMatchingPassword',
                            ]
                        )
                    );
                }

                if (!$this->pdoAuth->updatePassword($userId, $newUserPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userId,
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
