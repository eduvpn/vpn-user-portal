<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Auth\DbCredentialValidator;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Storage;
use LC\Portal\TplInterface;

class PasswdModule implements ServiceModuleInterface
{
    private DbCredentialValidator $dbCredentialValidator;

    private TplInterface $tpl;

    private Storage $storage;

    public function __construct(DbCredentialValidator $dbCredentialValidator, TplInterface $tpl, Storage $storage)
    {
        $this->dbCredentialValidator = $dbCredentialValidator;
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    public function init(Service $service): void
    {
        $service->get(
            '/passwd',
            function (UserInfo $userInfo, Request $request): Response {
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
            function (UserInfo $userInfo, Request $request): Response {
                $userPass = $request->requirePostParameter('userPass');
                $newUserPass = InputValidation::userPass($request->requirePostParameter('newUserPass'));
                $newUserPassConfirm = InputValidation::userPass($request->requirePostParameter('newUserPassConfirm'));

                if (!$this->dbCredentialValidator->isValid($userInfo->getUserId(), $userPass)) {
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

                $passwordHash = password_hash($newUserPass, \PASSWORD_DEFAULT);
                if (!\is_string($passwordHash)) {
                    throw new HttpException('unable to generate password hash', 500);
                }
                $this->storage->localUserUpdatePassword($userInfo->getUserId(), $passwordHash);

                return new RedirectResponse($request->getRootUri().'account', 302);
            }
        );
    }
}
