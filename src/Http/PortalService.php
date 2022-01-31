<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Auth\NullAuthModule;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\TplInterface;

/**
 * Used from "index.php".
 */
class PortalService extends Service implements ServiceInterface
{
    private TplInterface $tpl;

    public function __construct(TplInterface $tpl)
    {
        // the "real" authentication module will be set later
        // XXX make a class that crashes and burns when it is has not been replaced!
        parent::__construct(new NullAuthModule());
        $this->tpl = $tpl;
    }

    public function setAuthModule(AuthModuleInterface $authModule): void
    {
        $this->authModule = $authModule;
        $this->authModule->init($this);
    }

    public function run(Request $request): Response
    {
        try {
            return parent::run($request);
        } catch (HttpException $e) {
            return new HtmlResponse(
                $this->tpl->render(
                    'errorPage',
                    [
                        'code' => $e->statusCode(),
                        'message' => $e->getMessage(),
                    ]
                ),
                $e->responseHeaders(),
                $e->statusCode()
            );
        }
    }
}
