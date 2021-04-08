<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

/**
 * This hook is used to be able to switch the language without requiring to be
 * authenticated. As the language switcher is only a user preference stored in
 * a cookie this is not a problem. This way, even the authentication page can
 * use the language switcher.
 */
class LanguageSwitcherHook extends AbstractHook implements BeforeHookInterface
{
    private CookieInterface $cookie;

    /** @var array */
    private $supportedLanguages;

    public function __construct(array $supportedLanguages, CookieInterface $cookie)
    {
        $this->supportedLanguages = $supportedLanguages;
        $this->cookie = $cookie;
    }

    public function beforeAuth(Request $request): ?Response
    {
        if ('POST' !== $request->getRequestMethod()) {
            return null;
        }

        if ('/setLanguage' !== $request->getPathInfo()) {
            return null;
        }

        if (null === $language = $request->optionalPostParameter('setLanguage')) {
            $language = 'en_US';
        }
        if (!\in_array($language, $this->supportedLanguages, true)) {
            throw new HttpException('invalid language', 400);
        }

        $this->cookie->set('ui_lang', $language);

        return new RedirectResponse($request->requireHeader('HTTP_REFERER'), 302);
    }
}
