<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\OpenVpn;

use DateTimeInterface;
use LC\Portal\Config\PortalConfig;
use LC\Portal\OpenVpn\Exception\ConnectionException;
use LC\Portal\Storage;

class Connection
{
    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\Portal\Storage */
    private $storage;

    /**
     * @param \LC\Portal\Config\PortalConfig $portalConfig
     * @param \LC\Portal\Storage             $storage
     */
    public function __construct(PortalConfig $portalConfig, Storage $storage)
    {
        $this->portalConfig = $portalConfig;
        $this->storage = $storage;
    }

    public function connect(string $profileId, string $commonName, string $ipFour, string $ipSix, DateTimeInterface $connectedAt): void
    {
        // XXX add logging again
        // verify if certificate with CN still exists
        if (false === $userCertificateInfo = $this->storage->getUserCertificateInfo($commonName)) {
            throw new ConnectionException(sprintf('common name "%s" does not exist', $commonName));
        }

        // make sure the user is not disabled
        $userId = $userCertificateInfo['user_id'];
        if ($userCertificateInfo['user_is_disabled']) {
            throw new ConnectionException(sprintf('account of user "%s" is disabled', $userId));
        }

        // check whether ACLs are enabled
        $profileConfig = $this->portalConfig->getProfileConfig($profileId);
        if ($profileConfig->getEnableAcl()) {
            // make sure the user has the required permissions...
            $userPermissionList = $this->storage->getPermissionList($userId);
            if (false === self::hasPermission($userPermissionList, $profileConfig->getAclPermissionList())) {
                throw new ConnectionException(sprintf('user "%s" does not have the required permissions', $userId));
            }
        }

        $this->storage->clientConnect($profileId, $commonName, $ipFour, $ipSix, $connectedAt);
    }

    public function disconnect(string $profileId, string $commonName, string $ipFour, string $ipSix, DateTimeInterface $connectedAt, DateTimeInterface $disconnectedAt, int $bytesTransferred): void
    {
        $this->storage->clientDisconnect($profileId, $commonName, $ipFour, $ipSix, $connectedAt, $disconnectedAt, $bytesTransferred);
    }

    /**
     * @param array<string> $userPermissionList
     * @param array<string> $aclPermissionList
     */
    private static function hasPermission(array $userPermissionList, array $aclPermissionList): bool
    {
        // one of the permissions must be listed in the profile ACL list
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
