<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Exception\LdapClientException;
use LC\Portal\LdapClient;
use Psr\Log\LoggerInterface;

class LdapAuth implements CredentialValidatorInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\Portal\LdapClient */
    private $ldapClient;

    /** @var string */
    private $bindDnTemplate;

    /** @var string|null */
    private $baseDn;

    /** @var string|null */
    private $userFilterTemplate;

    /** @var array<string> */
    private $permissionAttributeList;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param LdapClient               $ldapClient
     * @param string                   $bindDnTemplate
     * @param string|null              $baseDn
     * @param string|null              $userFilterTemplate
     * @param array<string>            $permissionAttributeList
     */
    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, $bindDnTemplate, $baseDn, $userFilterTemplate, array $permissionAttributeList)
    {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->bindDnTemplate = $bindDnTemplate;
        $this->baseDn = $baseDn;
        $this->userFilterTemplate = $userFilterTemplate;
        $this->permissionAttributeList = $permissionAttributeList;
    }

    /**
     * @param string $authUser
     * @param string $authPass
     *
     * @return false|UserInfo
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
                // XXX do we need to escape filter here? we have no ldap to
                // test with at the moment :(
                $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->userFilterTemplate);
            }

            return new UserInfo($authUser, $this->getPermissionList($baseDn, $userFilter));
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
     *
     * @return array<string>
     */
    private function getPermissionList($baseDn, $userFilter)
    {
        if (0 === \count($this->permissionAttributeList)) {
            return [];
        }

        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            $this->permissionAttributeList
        );

        if (0 === $ldapEntries['count']) {
            // user does not exist
            return [];
        }

        $permissionList = [];
        foreach ($this->permissionAttributeList as $permissionAttribute) {
            foreach (self::extractPermission($ldapEntries, $permissionAttribute) as $attributeValue) {
                $permissionList[] = sprintf('%s!%s', $permissionAttribute, $attributeValue);
            }
        }

        return $permissionList;
    }

    /**
     * @param array  $ldapEntries
     * @param string $permissionAttribute
     *
     * @return array<string>
     */
    private static function extractPermission(array $ldapEntries, $permissionAttribute)
    {
        if (0 === $ldapEntries[0]['count']) {
            // attribute not found for this user
            return [];
        }

        return \array_slice($ldapEntries[0][strtolower($permissionAttribute)], 1);
    }
}
