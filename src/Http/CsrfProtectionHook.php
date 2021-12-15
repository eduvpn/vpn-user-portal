<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Http\Exception\HttpException;

/**
 * XXX look at php-saml-sp for better CSRF protection.
 */
class CsrfProtectionHook extends AbstractHook implements BeforeHookInterface
{
    public function beforeAuth(Request $request): ?Response
    {
        if (!$request->isBrowser()) {
            // not a browser, no CSRF protected needed
            // XXX is this actually true? What about XmlHttpRequest?
            return null;
        }

        // safe methods
        if (\in_array($request->getRequestMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $uriAuthority = $request->getScheme().'://'.$request->getAuthority();

        if (null !== $httpOrigin = $request->optionalHeader('HTTP_ORIGIN')) {
            $this->verifyOrigin($uriAuthority, $httpOrigin);

            return null;
        }
        if (null !== $httpReferrer = $request->optionalHeader('HTTP_REFERER')) {
            $this->verifyReferrer($uriAuthority, $httpReferrer);

            return null;
        }

        throw new HttpException('CSRF protection failed, no HTTP_ORIGIN or HTTP_REFERER', 400);
    }

    private function verifyOrigin(string $uriAuthority, string $httpOrigin): void
    {
        // the HTTP_ORIGIN MUST be equal to uriAuthority
        if ($uriAuthority !== $httpOrigin) {
            throw new HttpException('CSRF protection failed: unexpected HTTP_ORIGIN', 400);
        }
    }

    private function verifyReferrer(string $uriAuthority, string $httpReferrer): void
    {
        // the HTTP_REFERER MUST start with uriAuthority
        if (0 !== strpos($httpReferrer, sprintf('%s/', $uriAuthority))) {
            throw new HttpException('CSRF protection failed: unexpected HTTP_REFERER', 400);
        }
    }
}
