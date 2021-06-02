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
    private AuthModuleInterface $authModule;

    public function __construct(SessionInterface $session, Storage $storage, AuthModuleInterface $authModule)
    {
        $this->session = $session;
        $this->storage = $storage;
        $this->authModule = $authModule;
    }

    public function afterAuth(UserInfo $userInfo, Request $request): ?Response
    {
        // XXX somehow when logout is called this results in a loop where
        // the logout keeps getting called...
        // XXX maybe set a "_user_info_performing_logout" session variable,
        // that if set just deletes this session variable and returns null?

        // only update the user info once per browser session, not on every
        // request
        if ('yes' === $this->session->get('_user_info_already_updated')) {
            if (false === $this->storage->userExists($userInfo->userId())) {
                // but if the user account was removed in the meantime,
                // i.e. "Delete Account Data" trigger logout (if supported)
                if ($this->authModule->supportsLogout()) {
                    return $this->authModule->triggerLogout($request);
                }

                // user was deleted, but we don't know how to logout the user,
                // give up
                throw new HttpException('user account has been deleted', 403);
            }

            return null;
        }

        if (false === $this->storage->userExists($userInfo->userId())) {
            $this->storage->userAdd($userInfo->userId(), $userInfo->permissionList());
            $this->session->set('_user_info_already_updated', 'yes');

            return null;
        }

        // update permissionList
        // XXX we should implement "last seen" here also so we can delete old user accounts
        // (that are not disabled. Or does all user data gets deleted anyway?)
        $this->storage->userUpdate($userInfo->userId(), $userInfo->permissionList());
        $this->session->set('_user_info_already_updated', 'yes');

        return null;
    }
}
