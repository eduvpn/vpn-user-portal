<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Common\Http\CredentialValidatorInterface;
use LC\Common\Http\UserInfo;
use LC\Portal\Exception\LdapClientException;
use Psr\Log\LoggerInterface;

class LdapAuth implements CredentialValidatorInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var LdapClient */
    private $ldapClient;

    /** @var string */
    private $bindDnTemplate;

    /** @var string|null */
    private $baseDn;

    /** @var string|null */
    private $userFilterTemplate;

    /** @var string|null */
    private $userIdAttribute;

    /** @var string|null */
    private $permissionAttribute;

    /**
     * @param string      $bindDnTemplate
     * @param string|null $baseDn
     * @param string|null $userFilterTemplate
     * @param string|null $userIdAttribute
     * @param string|null $permissionAttribute
     */
    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, $bindDnTemplate, $baseDn, $userFilterTemplate, $userIdAttribute, $permissionAttribute)
    {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->bindDnTemplate = $bindDnTemplate;
        $this->baseDn = $baseDn;
        $this->userFilterTemplate = $userFilterTemplate;
        $this->userIdAttribute = $userIdAttribute;
        $this->permissionAttribute = $permissionAttribute;
    }

    /**
     * @param string $authUser
     * @param string $authPass
     *
     * @return false|\LC\Common\Http\UserInfo
     */
    public function isValid($authUser, $authPass)
    {
        $bindDn = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->bindDnTemplate);
        try {
            $this->ldapClient->bind($bindDn, $authPass);

            $baseDn = $bindDn;
            if (null !== $this->baseDn) {
                $baseDn = $this->baseDn;
            }

            $userFilter = '(objectClass=*)';
            if (null !== $this->userFilterTemplate) {
                $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->userFilterTemplate);
            }

            $userId = $authUser;
            $permissionList = [];

            if (null !== $userIdAttribute = $this->userIdAttribute) {
                // normalize the userId by querying it from the LDAP, benefits:
                // (1) we get the exact same capitalization as in the LDAP
                // (2) we can take a completely different attribute as the user
                //     id, e.g. mail, ipaUniqueID, ...
                if (null !== $directoryUserId = $this->getUserId($baseDn, $userFilter, $userIdAttribute)) {
                    $userId = $directoryUserId;
                }
            }

            // obtain permissions
            if (null !== $permissionAttribute = $this->permissionAttribute) {
                $permissionList = $this->getPermissionList($baseDn, $userFilter, $permissionAttribute);
            }

            return new UserInfo($userId, $permissionList);
        } catch (LdapClientException $e) {
            $this->logger->warning(
                sprintf('unable to bind with DN "%s" (%s)', $bindDn, $e->getMessage())
            );

            return false;
        }
    }

    /**
     * @param string $baseDn
     * @param string $userFilter
     * @param string $userIdAttribute
     *
     * @return string|null
     */
    private function getUserId($baseDn, $userFilter, $userIdAttribute)
    {
        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            [$userIdAttribute]
        );

        // it turns out that PHP's LDAP client converts the attribute name to
        // lowercase before populating the array...
        if (isset($ldapEntries[0][strtolower($userIdAttribute)][0])) {
            return $ldapEntries[0][strtolower($userIdAttribute)][0];
        }

        return null;
    }

    /**
     * @param string $baseDn
     * @param string $userFilter
     * @param string $permissionAttribute
     *
     * @return array<string>
     */
    private function getPermissionList($baseDn, $userFilter, $permissionAttribute)
    {
        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            [$permissionAttribute]
        );

        // it turns out that PHP's LDAP client converts the attribute name to
        // lowercase before populating the array...
        if (isset($ldapEntries[0][strtolower($permissionAttribute)][0])) {
            return \array_slice($ldapEntries[0][strtolower($permissionAttribute)], 1);
        }

        return [];
    }
}
