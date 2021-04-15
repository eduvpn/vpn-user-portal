<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Storage;

/**
 * Create a user in the users table if the user does not yet exists, or
 * update the stored user info in case the user *does* exist. Only once
 * per session.
 */
class UpdateUserInfoHook extends AbstractHook implements BeforeHookInterface
{
    private SessionInterface $session;
    private Storage $storage;

    public function __construct(SessionInterface $session, Storage $storage)
    {
        $this->session = $session;
        $this->storage = $storage;
    }

    public function afterAuth(UserInfo $userInfo, Request $request): ?Response
    {
        // only update the user info once per browser session, not on every
        // request
        if ('yes' === $this->session->get('_user_info_already_updated')) {
            if (false === $this->storage->userExists($userInfo->getUserId())) {
                // but if the user account was removed in the meantime,
                // destroy the session...
                // XXX is this acceptable? it doesn't work for SAML logins, should we trigger logout using AuthMethod here to share code path?
                $this->session->destroy();

                return new RedirectResponse($request->getUri());
            }

            return null;
        }

        if (false === $this->storage->userExists($userInfo->getUserId())) {
            $this->storage->userAdd($userInfo->getUserId(), $userInfo->getPermissionList());
            $this->session->set('_user_info_already_updated', 'yes');

            return null;
        }

        // update permissionList
        $this->storage->userUpdate($userInfo->getUserId(), $userInfo->getPermissionList());
        $this->session->set('_user_info_already_updated', 'yes');

        return null;
    }
}
