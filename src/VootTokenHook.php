<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use fkooman\SeCookie\SessionInterface;
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
    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var \SURFnet\VPN\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\OAuth\Client\OAuthClient */
    private $client;

    /** @var \fkooman\OAuth\Client\Provider */
    private $provider;

    /** @var string */
    private $vootUri;

    /**
     * @param \fkooman\SeCookie\SessionInterface          $session
     * @param \SURFnet\VPN\Common\HttpClient\ServerClient $serverClient
     * @param \fkooman\OAuth\Client\OAuthClient           $client
     * @param \fkooman\OAuth\Client\Provider              $provider
     * @param string                                      $vootUri
     */
    public function __construct(SessionInterface $session, ServerClient $serverClient, OAuthClient $client, Provider $provider, $vootUri)
    {
        $this->session = $session;
        $this->serverClient = $serverClient;
        $this->client = $client;
        $this->provider = $provider;
        $this->vootUri = $vootUri;
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
        $userInfo = $hookData['auth'];

        // check if we have the group info in the cache
        if ($this->session->has('_cached_voot_groups')) {
            // does it match the current userId?
            if ($userInfo->id() === $this->session->get('_cached_voot_groups_user_id')) {
                $vootGroups = $this->session->get('_cached_voot_groups');
                $hookData['auth']->addEntitlements($vootGroups);

                return true;
            }
            // does not match expected user... fetch again!
        }

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

        if (false === $response = $this->client->get($this->provider, $userInfo->id(), 'groups', $this->vootUri)) {
            // "false" is returned for a number of reasons:
            // * no access_token yet for this user ID / scope
            // * access_token expired (and no refresh_token available)
            // * access_token was not accepted (revoked?)
            // * refresh_token was rejected (revoked?)
            //
            // we need to re-request authorization at the OAuth server, redirect
            // the browser to the authorization endpoint (with a 302)
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

        if (!$response->isOkay()) {
            // VOOT responses should be HTTP 200 responses... there is
            // something else wrong...
            // XXX we should probably die here!
            return false;
        }

        $vootGroups = self::extractMembership($response->json());
        $this->session->set('_cached_voot_groups', $vootGroups);
        $this->session->set('_cached_voot_groups_user_id', $userInfo->id());
        $hookData['auth']->addEntitlements($vootGroups);

        return true;
    }

    /**
     * @return array<string>
     */
    private static function extractMembership(array $responseData)
    {
        $memberOf = [];
        foreach ($responseData as $groupEntry) {
            if (!\is_array($groupEntry)) {
                continue;
            }
            if (!array_key_exists('id', $groupEntry)) {
                continue;
            }
            if (!\is_string($groupEntry['id'])) {
                continue;
            }
            $memberOf[] = $groupEntry['id'];
        }

        return $memberOf;
    }
}
