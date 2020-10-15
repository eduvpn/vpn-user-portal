<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\BeforeHookInterface;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\Http\SessionInterface;
use LC\Common\Http\UserInfo;
use LC\Common\TplInterface;

class IrmaAuthentication implements ServiceModuleInterface, BeforeHookInterface
{
    /** @var \LC\Common\TplInterface */
    protected $tpl;

    /** @var SessionInterface */
    private $session;

    public function __construct(SessionInterface $session, TplInterface $tpl)
    {
        $this->session = $session;
        $this->tpl = $tpl;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/just/a/hook',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request) {
                // maybe you need a FORM post? maybe not?
                return new RedirectResponse('/', 302);
            }
        );
    }

    /**
     * @return \LC\Common\Http\UserInfo|\LC\Common\Http\Response
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (null !== $authUser = $this->session->get('_irma_auth_user')) {
            return new UserInfo(
                $authUser,
                []
            );
        }

        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'irmaAuthentication',
                [
                ]
            )
        );

        return $response;
    }
}
