<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use Vpn\Portal\Storage;

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
        // only update the user info once per browser session, not on every
        // request
        if ('yes' === $this->session->get('_user_info_already_updated')) {
            if (false === $this->storage->userExists($userInfo->userId())) {
                // user was deleted (by admin) during the active session, so
                // we force a logout
                $this->session->destroy();

                // XXX the referrer is not necessarily set, so we have to return to the current URL
                // I do not know why this works...
                return $this->authModule->triggerLogout($request);
            }

            return null;
        }

        if (false === $this->storage->userExists($userInfo->userId())) {
            // user does not yet exist in the database, create it
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
