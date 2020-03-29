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
    private $permissionAttribute;

    /**
     * @param string      $bindDnTemplate
     * @param string|null $baseDn
     * @param string|null $userFilterTemplate
     * @param string|null $permissionAttribute
     */
    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, $bindDnTemplate, $baseDn, $userFilterTemplate, $permissionAttribute)
    {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->bindDnTemplate = $bindDnTemplate;
        $this->baseDn = $baseDn;
        $this->userFilterTemplate = $userFilterTemplate;
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
        if (null === $this->permissionAttribute) {
            return [];
        }

        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            [$this->permissionAttribute]
        );

        if (0 === $ldapEntries['count']) {
            // user does not exist
            return [];
        }

        return self::extractPermission($ldapEntries, $this->permissionAttribute);
    }

    /**
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
