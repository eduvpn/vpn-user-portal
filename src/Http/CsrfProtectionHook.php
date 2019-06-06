<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class CsrfProtectionHook implements BeforeHookInterface
{
    public function executeBefore(Request $request, array $hookData): void
    {
        if (!$request->isBrowser()) {
            // not a browser, no CSRF protected needed
            return;
        }

        // safe methods
        if (\in_array($request->getRequestMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // POST to /_saml/acs is allowed without matching REFERER, it comes
        // from the IdP...
        if (Service::isWhitelisted($request, ['POST' => ['/_saml/acs']])) {
            return;
        }

        $serverOrigin = $request->getScheme().'://'.$request->getAuthority();
        if (null !== $httpOrigin = $request->optionalHeader('HTTP_ORIGIN')) {
            $this->verifyOrigin($serverOrigin, $httpOrigin);

            return;
        }

        if (null !== $httpReferrer = $request->optionalHeader('HTTP_REFERER')) {
            $this->verifyReferrer($serverOrigin, $httpReferrer);

            return;
        }

        throw new HttpException('CSRF protection failed, no HTTP_ORIGIN or HTTP_REFERER', 400);
    }

    public function verifyOrigin(string $serverOrigin, string $httpOrigin): void
    {
        // the HTTP_ORIGIN MUST be equal to uriAuthority
        if ($serverOrigin !== $httpOrigin) {
            throw new HttpException('CSRF protection failed: unexpected HTTP_ORIGIN', 400);
        }
    }

    public function verifyReferrer(string $serverOrigin, string $httpReferrer): void
    {
        // the HTTP_REFERER MUST start with uriAuthority
        if (0 !== strpos($httpReferrer, sprintf('%s/', $serverOrigin))) {
            throw new HttpException('CSRF protection failed: unexpected HTTP_REFERER', 400);
        }
    }
}
