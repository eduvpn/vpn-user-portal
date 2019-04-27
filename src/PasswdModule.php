<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\InputValidation;
use LC\Portal\Http\RedirectResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\ServiceModuleInterface;

class PasswdModule implements ServiceModuleInterface
{
    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /** @var \LC\Portal\Storage */
    private $storage;

    public function __construct(TplInterface $tpl, Storage $storage)
    {
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/passwd',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalPasswd',
                        [
                            'userId' => $userInfo->getUserId(),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/passwd',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $userPass = $request->getPostParameter('userPass');
                $newUserPass = InputValidation::userPass($request->getPostParameter('newUserPass'));
                $newUserPassConfirm = InputValidation::userPass($request->getPostParameter('newUserPassConfirm'));

                if (!$this->storage->isValid($userInfo->getUserId(), $userPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->getUserId(),
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
                                'userId' => $userInfo->getUserId(),
                                'errorCode' => 'noMatchingPassword',
                            ]
                        )
                    );
                }

                if (!$this->storage->updatePassword($userInfo->getUserId(), $newUserPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->getUserId(),
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
