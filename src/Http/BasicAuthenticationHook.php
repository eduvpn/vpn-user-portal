<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\HttpException;

class BasicAuthenticationHook implements BeforeHookInterface
{
    /** @var array<string,string> */
    private $userPass;

    /** @var string */
    private $realm;

    /**
     * @param array<string,string> $userPass
     * @param string               $realm
     */
    public function __construct(array $userPass, $realm = 'Protected Area')
    {
        $this->userPass = $userPass;
        $this->realm = $realm;
    }

    /**
     * @return UserInfo
     */
    public function executeBefore(Request $request, array $hookData)
    {
        $authUser = $request->optionalHeader('PHP_AUTH_USER');
        $authPass = $request->optionalHeader('PHP_AUTH_PW');
        if (null === $authUser || null === $authPass) {
            throw new HttpException(
                'missing authentication information',
                401,
                ['WWW-Authenticate' => sprintf('Basic realm="%s"', $this->realm)]
            );
        }

        if (\array_key_exists($authUser, $this->userPass)) {
            if (hash_equals($authPass, $this->userPass[$authUser])) {
                return new UserInfo($authUser, []);
            }
        }

        throw new HttpException(
            'invalid authentication information',
            401,
            ['WWW-Authenticate' => sprintf('Basic realm="%s"', $this->realm)]
        );
    }
}
