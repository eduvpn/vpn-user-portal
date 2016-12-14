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
use SURFnet\VPN\Common\Http\Exception\HttpException;
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

    /**
     * Execute a hook before routing.
     *
     * @param Request $request  the HTTP request
     * @param array   $hookData results from previously called hooks, we need
     *                          the results from the "auth" hook
     *
     * @return \SURFnet\VPN\Common\Http\RedirectResponse|bool returns the RedirectResponse if there is no Voot token available yet, returns true
     *                                                        if a Voot token is already available and returns false if this is not
     *                                                        the time to check for a Voot token, e.g. in the process of obtaining one
     */
    public function executeBefore(Request $request, array $hookData)
    {
        if (!array_key_exists('auth', $hookData)) {
            throw new HttpException('authentication hook did not run before', 500);
        }
        $userId = $hookData['auth'];

        // do not get involved in POST requests, only in GETs
        if ('GET' !== $request->getRequestMethod()) {
            return false;
        }

        // but not when we already try to obtain the access token to avoid
        // redirect loops
        if ('/_voot/authorize' === $request->getPathInfo()) {
            return false;
        }
        if ('/_voot/callback' === $request->getPathInfo()) {
            return false;
        }

        if (!$this->serverClient->get('has_voot_token', ['user_id' => $userId])) {
            return new RedirectResponse(
                sprintf(
                    '%s_voot/authorize?%s',
                    $request->getRootUri(),
                    http_build_query(
                        ['return_to' => $request->getUri()]
                    )
                ),
                302
            );
        }

        return true;
    }
}
