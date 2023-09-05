<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
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
            $ldapAuthConfig->ldapUri(),
            $ldapAuthConfig->tlsCa(),
            $ldapAuthConfig->tlsCert(),
            $ldapAuthConfig->tlsKey()
        );
    }

    /**
     * Validate a user's credentials are return the (normalized) internal
     * user ID to use.
     */
    public function validate(string $authUser, string $authPass): UserInfo
    {
        $bindDn = $this->authUserToDn($authUser);

        try {
            $this->ldapClient->bind($bindDn, $authPass);

            // we "normalize" the `userId` by also requesting the
            // `userIdAttribute` from the directory together with the
            // permission attribute(s) in order to be able to uniquely identify
            // the user as LDAP authentication is "case insensitive"
            $userIdAttribute = $this->ldapAuthConfig->userIdAttribute();
            $attributeNameValueList = $this->attributesForDn(
                $bindDn,
                array_merge(
                    [$userIdAttribute],
                    $this->ldapAuthConfig->permissionAttributeList()
                )
            );

            // update userId with the "normalized" value from the LDAP server
            if (!isset($attributeNameValueList[$userIdAttribute][0])) {
                throw new CredentialValidatorException(sprintf('unable to find userIdAttribute (%s) in LDAP result', $userIdAttribute));
            }
            $userId = $attributeNameValueList[$userIdAttribute][0];

            return new UserInfo(
                $userId,
                self::flattenPermissionList($attributeNameValueList, $this->ldapAuthConfig->permissionAttributeList())
            );
        } catch (LdapClientException $e) {
            // convert LDAP errors into `CredentialValidatorException`
            throw new CredentialValidatorException($e->getMessage());
        }
    }

    private function authUserToDn(string $authUser): string
    {
        try {
            // add "realm" after user name if none is specified
            if (null !== $addRealm = $this->ldapAuthConfig->addRealm()) {
                if (false === strpos($authUser, '@')) {
                    $authUser .= '@'.$addRealm;
                }
            }

            if (null !== $bindDnTemplate = $this->ldapAuthConfig->bindDnTemplate()) {
                // we have a bind DN template to bind to the LDAP with the user's
                // provided "Username", so use that
                return str_replace('{{UID}}', LdapClient::escapeDn($authUser), $bindDnTemplate);
            }

            // Do (anonymous) LDAP bind to find the DN based on
            // userFilterTemplate
            $this->ldapClient->bind($this->ldapAuthConfig->searchBindDn(), $this->ldapAuthConfig->searchBindPass());
            $userFilter = str_replace('{{UID}}', LdapClient::escapeFilter($authUser), $this->ldapAuthConfig->userFilterTemplate());
            if (null === $ldapResult = $this->ldapClient->search($this->ldapAuthConfig->baseDn(), $userFilter)) {
                throw new CredentialValidatorException(sprintf('no such user "%s"', $authUser));
            }

            return $ldapResult['dn'];
        } catch (LdapClientException $e) {
            // convert LDAP errors into `CredentialValidatorException`
            throw new CredentialValidatorException($e->getMessage());
        }
    }

    /**
     * Get requested attributes for DN.
     *
     * If no attributes are available, or the user no longer exists, an empty
     * array is returned.
     *
     * @param array<string> $attributeNameList
     *
     * @return array<string,array<string>>
     */
    private function attributesForDn(string $userDn, array $attributeNameList): array
    {
        if (0 === count($attributeNameList)) {
            // no attributes requested
            return [];
        }

        try {
            if (null === $searchResult = $this->ldapClient->search($userDn, null, $attributeNameList)) {
                throw new CredentialValidatorException(sprintf('no such DN "%s"', $userDn));
            }

            return $searchResult['result'];
        } catch (LdapClientException $e) {
            // convert LDAP errors into `CredentialValidatorException`
            throw new CredentialValidatorException($e->getMessage());
        }
    }

    /**
     * Flatten the array by merging the values of all attributes in one array.
     *
     * @param array<string,array<string>> $attributeNameValueList
     * @param array<string> $permissionAttributeList
     *
     * @return array<string>
     */
    private static function flattenPermissionList(array $attributeNameValueList, array $permissionAttributeList): array
    {
        $permissionList = [];
        foreach ($permissionAttributeList as $permissionAttribute) {
            if (array_key_exists($permissionAttribute, $attributeNameValueList)) {
                $permissionList = array_merge($permissionList, $attributeNameValueList[$permissionAttribute]);
            }
        }

        return array_values(array_unique($permissionList));
    }
}
