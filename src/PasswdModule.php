<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\TplInterface;

class PasswdModule implements ServiceModuleInterface
{
    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var \LC\Portal\Storage */
    private $storage;

    public function __construct(TplInterface $tpl, Storage $storage)
    {
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/passwd',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $userPass = $request->requirePostParameter('userPass');
                $newUserPass = InputValidation::userPass($request->requirePostParameter('newUserPass'));
                $newUserPassConfirm = InputValidation::userPass($request->requirePostParameter('newUserPassConfirm'));

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
