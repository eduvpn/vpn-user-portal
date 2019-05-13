<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Node;

use DateTime;
use LC\Portal\CA\CaInterface;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Node\Exception\NodeException;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

class LocalNodeApi implements NodeApiInterface
{
    /** @var \LC\Portal\CA\CaInterface */
    private $ca;

    /** @var \LC\Portal\TlsCrypt */
    private $tlsCrypt;

    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\Portal\Storage */
    private $storage;

    /**
     * @param \LC\Portal\CA\CaInterface      $ca
     * @param \LC\Portal\TlsCrypt            $tlsCrypt
     * @param \LC\Portal\Config\PortalConfig $portalConfig
     * @param \LC\Portal\Storage             $storage
     */
    public function __construct(CaInterface $ca, TlsCrypt $tlsCrypt, PortalConfig $portalConfig, Storage $storage)
    {
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->portalConfig = $portalConfig;
        $this->storage = $storage;
    }

    /**
     * @return array<string, \LC\Portal\Config\ProfileConfig>
     */
    public function getProfileList()
    {
        return $this->portalConfig->getProfileConfigList();
    }

    /**
     * @param string    $profileId
     * @param string    $commonName
     * @param string    $ipFour
     * @param string    $ipSix
     * @param \DateTime $connectedAt
     *
     * @return void
     */
    public function connect($profileId, $commonName, $ipFour, $ipSix, DateTime $connectedAt)
    {
        // XXX add logging again
        // verify if certificate with CN still exists
        if (false === $userCertificateInfo = $this->storage->getUserCertificateInfo($commonName)) {
            throw new NodeException(sprintf('common name "%s" does not exist', $commonName));
        }

        // make sure the user is not disabled
        $userId = $userCertificateInfo['user_id'];
        if ($userCertificateInfo['user_is_disabled']) {
            throw new NodeException(sprintf('account of user "%s" is disabled', $userId));
        }

        // check whether ACLs are enabled
        $profileConfig = $this->portalConfig->getProfileConfig($profileId);
        if ($profileConfig->getEnableAcl()) {
            // make sure the user has the required permissions...
            $userPermissionList = $this->storage->getPermissionList($userId);
            if (false === self::hasPermission($userPermissionList, $profileConfig->getAclPermissionList())) {
                throw new NodeException(sprintf('user "%s" does not have the required permissions', $userId));
            }
        }

        $this->storage->clientConnect($profileId, $commonName, $ipFour, $ipSix, $connectedAt);
    }

    /**
     * @param string    $profileId
     * @param string    $commonName
     * @param string    $ipFour
     * @param string    $ipSix
     * @param \DateTime $connectedAt
     * @param \DateTime $disconnectedAt
     * @param int       $bytesTransferred
     *
     * @return void
     */
    public function disconnect($profileId, $commonName, $ipFour, $ipSix, DateTime $connectedAt, DateTime $disconnectedAt, $bytesTransferred)
    {
        $this->storage->clientDisconnect($profileId, $commonName, $ipFour, $ipSix, $connectedAt, $disconnectedAt, $bytesTransferred);
    }

    /**
     * @param string $commonName
     *
     * @return array<string, string>
     */
    public function addServerCertificate($commonName)
    {
        $certInfo = $this->ca->serverCert($commonName);
        $certInfo['ca'] = $this->ca->caCert();
        $certInfo['tls-crypt'] = $this->tlsCrypt->get();

        return $certInfo;
    }

    /**
     * @param array<string> $userPermissionList
     * @param array<string> $aclPermissionList
     *
     * @return bool
     */
    private static function hasPermission(array $userPermissionList, array $aclPermissionList)
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
