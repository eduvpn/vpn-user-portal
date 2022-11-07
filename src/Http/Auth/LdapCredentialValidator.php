<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use Vpn\Portal\Cfg\LdapAuthConfig;
use Vpn\Portal\Exception\LdapClientException;
use Vpn\Portal\Http\Auth\Exception\CredentialValidatorException;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\LdapClient;
use Vpn\Portal\LoggerInterface;

class LdapCredentialValidator implements CredentialValidatorInterface
{
    private LdapAuthConfig $ldapAuthConfig;

    private LoggerInterface $logger;

    private LdapClient $ldapClient;

    public function __construct(LdapAuthConfig $ldapAuthConfig, LoggerInterface $logger)
    {
        $this->ldapAuthConfig = $ldapAuthConfig;
        $this->logger = $logger;
        $this->ldapClient = new LdapClient(
            $ldapAuthConfig->ldapUri()
        );
    }

    /**
     * @throws \Vpn\Portal\Http\Auth\Exception\CredentialValidatorException
     */
    public function validate(string $authUser, string $authPass): UserInfo
    {
        // add "realm" after user name if none is specified
        if (null !== $addRealm = $this->ldapAuthConfig->addRealm()) {
            if (false === strpos($authUser, '@')) {
                $authUser .= '@'.$addRealm;
            }
        }

        // get bind DN either from template, or from anonymous bind + search
        if (false === $bindDn = $this->getBindDn($authUser)) {
            throw new CredentialValidatorException('unable to find a DN to bind with');
        }

        try {
            $this->ldapClient->bind($bindDn, $authPass);

            $baseDn = $this->ldapAuthConfig->baseDn() ?? $bindDn;
            $userFilter = '(objectClass=*)';
            if (null !== $userFilterTemplate = $this->ldapAuthConfig->userFilterTemplate()) {
                $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $userFilterTemplate);
            }

            $userId = $authUser;
            if (null !== $userIdAttribute = $this->ldapAuthConfig->userIdAttribute()) {
                // normalize the userId by querying it from the LDAP, benefits:
                // (1) we get the exact same capitalization as in the LDAP
                // (2) we can take a completely different attribute as the user
                //     id, e.g. mail, ipaUniqueID, ...
                $userId = $this->getUserId($baseDn, $userFilter, $userIdAttribute);
            }

            return new UserInfo(
                $userId,
                $this->getPermissionList($baseDn, $userFilter, $this->ldapAuthConfig->permissionAttributeList())
            );
        } catch (LdapClientException $e) {
            // convert LDAP errors into `CredentialValidatorException`
            throw new CredentialValidatorException($e->getMessage());
        }
    }

    private function getUserId(string $baseDn, string $userFilter, string $userIdAttribute): string
    {
        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            [$userIdAttribute]
        );

        // it turns out that PHP's LDAP client converts the attribute name to
        // lowercase before populating the array...
        if (!isset($ldapEntries[0][strtolower($userIdAttribute)][0])) {
            // if the userIdAttribute is NOT set, fail, admin configuration error
            throw new CredentialValidatorException(sprintf('user ID attribute "%s" not available in LDAP response', $userIdAttribute));
        }

        return $ldapEntries[0][strtolower($userIdAttribute)][0];
    }

    /**
     * @return false|string
     */
    private function getBindDn(string $authUser)
    {
        if (null !== $bindDnTemplate = $this->ldapAuthConfig->bindDnTemplate()) {
            // we have a bind DN template to bind to the LDAP with the user's
            // provided "Username", so use that
            return str_replace('{{UID}}', LdapClient::escapeDn($authUser), $bindDnTemplate);
        }

        // we do not have a bind DN, so do an (anonymous) LDAP bind + search to
        // find a DN we can bind with based on userFilterTemplate
        $this->ldapClient->bind($this->ldapAuthConfig->searchBindDn(), $this->ldapAuthConfig->searchBindPass());
        if (null === $userFilterTemplate = $this->ldapAuthConfig->userFilterTemplate()) {
            $this->logger->error('"userFilterTemplate" not set, unable to search for DN');

            return false;
        }
        $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $userFilterTemplate);
        if (null === $baseDn = $this->ldapAuthConfig->baseDn()) {
            $this->logger->error('"baseDn" not set, unable to search for DN');

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
     * @param array<string> $permissionAttributeList
     *
     * @return array<string>
     */
    private function getPermissionList(string $baseDn, string $userFilter, array $permissionAttributeList): array
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
