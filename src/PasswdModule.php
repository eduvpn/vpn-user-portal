<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Http\CredentialValidatorInterface;
use LC\Portal\Http\HtmlResponse;
use LC\Portal\Http\InputValidation;
use LC\Portal\Http\RedirectResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Response;
use LC\Portal\Http\Service;
use LC\Portal\Http\ServiceModuleInterface;

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
            function (Request $request, array $hookData): Response {
                /** @var \LC\Portal\Http\UserInfo */
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
