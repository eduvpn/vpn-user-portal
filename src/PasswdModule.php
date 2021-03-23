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
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\TplInterface;

class PasswdModule implements ServiceModuleInterface
{
    private CredentialValidatorInterface $credentialValidator;

    private TplInterface $tpl;

    private Storage $storage;

    public function __construct(CredentialValidatorInterface $credentialValidator, TplInterface $tpl, Storage $storage)
    {
        $this->credentialValidator = $credentialValidator;
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/passwd',
            function (Request $request, array $hookData): Response {
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
            function (Request $request, array $hookData): Response {
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];

                $userPass = $request->requirePostParameter('userPass');
                $newUserPass = InputValidation::userPass($request->requirePostParameter('newUserPass'));
                $newUserPassConfirm = InputValidation::userPass($request->requirePostParameter('newUserPassConfirm'));

                if (!$this->credentialValidator->isValid($userInfo->getUserId(), $userPass)) {
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
