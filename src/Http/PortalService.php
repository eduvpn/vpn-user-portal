<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\TplInterface;

/**
 * Used from "index.php".
 */
class PortalService extends Service implements ServiceInterface
{
    private TplInterface $tpl;

    public function __construct(AuthModuleInterface $authModule, TplInterface $tpl)
    {
        parent::__construct($authModule);
        $this->tpl = $tpl;
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
