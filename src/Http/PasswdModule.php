<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Auth\DbCredentialValidator;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\Storage;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;

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

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/passwd',
            function (Request $request, UserInfo $userInfo): Response {
                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnPortalPasswd',
                        [
                            'userId' => $userInfo->userId(),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/passwd',
            function (Request $request, UserInfo $userInfo): Response {
                $userPass = $request->requirePostParameter('userPass', fn (string $s) => Validator::userAuthPass($s));
                $newUserPass = $request->requirePostParameter('newUserPass', fn (string $s) => Validator::userPass($s));
                $newUserPassConfirm = $request->requirePostParameter('newUserPassConfirm', fn (string $s) => Validator::userPass($s));

                if (!$this->dbCredentialValidator->isValid($userInfo->userId(), $userPass)) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalPasswd',
                            [
                                'userId' => $userInfo->userId(),
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
                                'userId' => $userInfo->userId(),
                                'errorCode' => 'noMatchingPassword',
                            ]
                        )
                    );
                }

                $passwordHash = password_hash($newUserPass, PASSWORD_DEFAULT);
                if (!\is_string($passwordHash)) {
                    throw new HttpException('unable to generate password hash', 500);
                }
                $this->storage->localUserUpdatePassword($userInfo->userId(), $passwordHash);

                return new RedirectResponse($request->getRootUri().'account');
            }
        );
    }
}
