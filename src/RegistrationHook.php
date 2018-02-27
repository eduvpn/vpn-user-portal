<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\Exception\HttpException;
use SURFnet\VPN\Common\Http\HtmlResponse;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\PdoAuth;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\TplInterface;

class RegistrationHook implements BeforeHookInterface
{
    /** @var \SURFnet\VPN\Common\TplInterface */
    private $tpl;

    /** @var \SURFnet\VPN\Common\Http\PdoAuth */
    private $pdoAuth;

    /** @var Voucher */
    private $voucher;

    public function __construct(TplInterface $tpl, PdoAuth $pdoAuth, Voucher $voucher)
    {
        $this->tpl = $tpl;
        $this->pdoAuth = $pdoAuth;
        $this->voucher = $voucher;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        switch ($request->getRequestMethod()) {
            case 'GET':
            case 'HEAD':
                if ('/register' !== $request->getPathInfo()) {
                    return false;
                }

                $voucherCode = InputValidation::voucherCode($request->getQueryParameter('voucherCode'));
                $voucherUserId = $this->voucher->getInfo($voucherCode);
                if (false === $voucherUserId) {
                    throw new HttpException(
                        'invalid "voucherCode"',
                        400
                    );
                }
                $response = new Response(200, 'text/html');
                $response->setBody(
                    $this->tpl->render(
                        'vpnPortalRegister',
                        [
                            '_form_auth_login_page' => false,
                            'voucherCode' => $voucherCode,
                            'voucherUserId' => $voucherUserId,
                        ]
                    )
                );

                return $response;
            case 'POST':
                if ('/register' !== $request->getPathInfo()) {
                    return false;
                }

                $voucherCode = InputValidation::voucherCode($request->getQueryParameter('voucherCode'));
                $voucherUserId = $this->voucher->getInfo($voucherCode);

                if (false === $voucherUserId) {
                    throw new HttpException(
                        'invalid "voucherCode"',
                        400
                    );
                }

                $userId = InputValidation::userId($request->getPostParameter('userName'));
                $userPass = InputValidation::userPass($request->getPostParameter('userPass'));
                $userPassConfirm = InputValidation::userPass($request->getPostParameter('userPassConfirm'));

                if ($userPass !== $userPassConfirm) {
                    return new HtmlResponse(
                        $this->tpl->render(
                            'vpnPortalRegister',
                            [
                                '_form_auth_login_page' => false,
                                'voucherCode' => $voucherCode,
                                'errorCode' => 'noMatchingPassword',
                                'voucherUserId' => $voucherUserId,
                                'userId' => $userId,
                            ]
                        )
                    );
                }

                // make sure the user does not exist
                if ($this->pdoAuth->userExists($userId)) {
                    throw new HttpException(
                        sprintf('user "%s" already exists', $userId),
                        400
                    );
                }

                if (!$this->voucher->useVoucher($userId, $voucherCode)) {
                    throw new HttpException(
                        'invalid "voucherCode"',
                        400
                    );
                }

                // add the user
                $this->pdoAuth->add(
                    $userId,
                    $userPass
                );

                return new RedirectResponse($request->getRootUri(), 302);
            default:
                return false;
        }
    }
}
