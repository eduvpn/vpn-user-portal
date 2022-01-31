<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

/**
 * Protect against *Cross Site Request Forgery* (CSRF) for "POST" requests.
 *
 * NOTE: this hook is strictly speaking not necessary for any modern browser,
 * just Internet Explorer as we also use SameSite=Strict cookies. That should
 * prevent any CSRF attack.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
 */
class CsrfProtectionHook extends AbstractHook implements HookInterface
{
    public function beforeAuth(Request $request): ?Response
    {
        // ignore GET, HEAD, OPTIONS as they have no side-effects...
        if (\in_array($request->getRequestMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        // we require the HTTP_REFERER to be set, if it is not this call
        // throws a HttpException
        $request->requireReferrer();

        return null;
    }
}
