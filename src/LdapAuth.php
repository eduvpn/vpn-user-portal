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

    /** @var string|null */
    private $bindDnTemplate;

    /** @var string|null */
    private $baseDn;

    /** @var string|null */
    private $userFilterTemplate;

    /** @var string|null */
    private $userIdAttribute;

    /** @var string|null */
    private $addRealm;

    /** @var array<string> */
    private $permissionAttributeList;

    /**
     * @param string|null   $bindDnTemplate
     * @param string|null   $baseDn
     * @param string|null   $userFilterTemplate
     * @param string|null   $userIdAttribute
     * @param string|null   $addRealm
     * @param array<string> $permissionAttributeList
     */
    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, $bindDnTemplate, $baseDn, $userFilterTemplate, $userIdAttribute, $addRealm, array $permissionAttributeList)
    {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->bindDnTemplate = $bindDnTemplate;
        $this->baseDn = $baseDn;
        $this->userFilterTemplate = $userFilterTemplate;
        $this->userIdAttribute = $userIdAttribute;
        $this->addRealm = $addRealm;
        $this->permissionAttributeList = $permissionAttributeList;
    }

    /**
     * @param string $authUser
     * @param string $authPass
     *
     * @return false|\LC\Common\Http\UserInfo
     */
    public function isValid($authUser, $authPass)
    {
        // add "realm" after user name if none is specified
        if (null !== $addRealm = $this->addRealm) {
            if (false === strpos($authUser, '@')) {
                $authUser .= '@'.$addRealm;
            }
        }

        // get bind DN either from template, or from anonymous bind + search
        if (false === $bindDn = $this->getBindDn($authUser)) {
            // unable to find a DN to bind with...
            return false;
        }

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

            return new UserInfo(
                $userId,
                $this->getPermissionList($baseDn, $userFilter, $this->permissionAttributeList)
            );
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
     * @param string $authUser
     *
     * @return string|false
     */
    private function getBindDn($authUser)
    {
        if (null !== $this->bindDnTemplate) {
            // we have a bind DN template to bind to the LDAP, so use that
            return str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->bindDnTemplate);
        }

        // we do not have a bind DN, so search for one based on userFilterTemplate
        $this->ldapClient->bind();
        if (null === $this->userFilterTemplate) {
            $this->logger->error('"userFilterTemplate" not set, unable to search for DN');

            return false;
        }
        $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->userFilterTemplate);
        if (null === $baseDn = $this->baseDn) {
            $this->logger->error('"baseDN" not set, unable to search for DN');

            return false;
        }
        $ldapEntries = $this->ldapClient->search($baseDn, $userFilter);
        if (!isset($ldapEntries[0]['dn'])) {
            // unable to find an entry in this baseDn with this filter
            return false;
        }

        return $ldapEntries[0]['dn'];
    }

    /**
     * @param string        $baseDn
     * @param string        $userFilter
     * @param array<string> $permissionAttributeList
     *
     * @return array<string>
     */
    private function getPermissionList($baseDn, $userFilter, array $permissionAttributeList)
    {
        if (0 === \count($permissionAttributeList)) {
            return [];
        }

        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            $permissionAttributeList
        );

        $permissionList = [];
        foreach ($permissionAttributeList as $permissionAttribute) {
            // it turns out that PHP's LDAP client converts the attribute name to
            // lowercase before populating the array...
            if (isset($ldapEntries[0][strtolower($permissionAttribute)][0])) {
                $permissionList = array_merge($permissionList, \array_slice($ldapEntries[0][strtolower($permissionAttribute)], 1));
            }
        }

        return $permissionList;
    }
}
