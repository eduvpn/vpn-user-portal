<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

use SURFnet\VPN\Common\Http\BeforeHookInterface;
use SURFnet\VPN\Common\Http\RedirectResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\HttpClient\ServerClient;

/**
 * This hook is used to make sure a VOOT token is available for the
 * authenticated user.
 */
class VootTokenHook implements BeforeHookInterface
{
    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    public function __construct(ServerClient $serverClient)
    {
        $this->serverClient = $serverClient;
    }

    public function executeBefore(Request $request, array $hookData)
    {
        // do not get involved in POST requests, only in simple GETs
        if ('GET' !== $request->getRequestMethod()) {
            return;
        }

        // but not when we already try to obtain the access token to avoid
        // redirect loops
        if ('/_voot/authorize' === $request->getPathInfo()) {
            return;
        }
        if ('/_voot/callback' === $request->getPathInfo()) {
            return;
        }

        $userId = $hookData['auth'];
        if (!$this->serverClient->hasVootToken($userId)) {
            $redirectUri = sprintf('%s%s', $request->getRootUri(), '_voot/authorize');

            return new RedirectResponse($redirectUri, 302);
        }

        return;
    }
}
