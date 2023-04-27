<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use Vpn\Portal\Dt;
use Vpn\Portal\FileIO;
use Vpn\Portal\Json;
use Vpn\Portal\Storage;

/**
 * Create a user in the users table if the user does not yet exists, or
 * update the stored user info in case the user *does* exist. Only once
 * per session.
 */
class UpdateUserInfoHook extends AbstractHook implements HookInterface
{
    protected DateTimeImmutable $dateTime;
    private SessionInterface $session;
    private Storage $storage;
    private AuthModuleInterface $authModule;
    private string $staticPermissionFile;

    public function __construct(SessionInterface $session, Storage $storage, AuthModuleInterface $authModule, string $staticPermissionFile)
    {
        $this->session = $session;
        $this->storage = $storage;
        $this->authModule = $authModule;
        $this->staticPermissionFile = $staticPermissionFile;
        $this->dateTime = Dt::get();
    }

    public function afterAuth(Request $request, UserInfo &$userInfo): ?Response
    {
        // add the "static" permissions obtained from
        // config/static_permissions.json
        $this->addStaticPermissions($userInfo);

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
            $this->storage->userAdd($userInfo, $this->dateTime);
            $this->session->set('_user_info_already_updated', 'yes');

            return null;
        }

        // update last seen & permissionList
        $this->storage->userUpdate($userInfo, $this->dateTime);
        $this->session->set('_user_info_already_updated', 'yes');

        return null;
    }

    private function addStaticPermissions(UserInfo &$userInfo): void
    {
        if (!FileIO::exists($this->staticPermissionFile)) {
            return;
        }
        $permissionData = Json::decode(FileIO::read($this->staticPermissionFile));
        $permissionList = $userInfo->permissionList();
        foreach ($permissionData as $permissionId => $userIdList) {
            if (!is_string($permissionId)) {
                continue;
            }
            if (!is_array($userIdList)) {
                continue;
            }
            if (in_array($userInfo->userId(), $userIdList, true)) {
                $permissionList[] = $permissionId;
            }
        }

        $permissionList = array_values(array_unique($permissionList));
        $userInfo->setPermissionList($permissionList);
    }
}
