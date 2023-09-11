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
        $bindDn = $this->authUserToDn($authUser, $authPass);

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

    private function authUserToDn(string $authUser, string $authPass): string
    {
        try {
            $searchBindDn = $this->ldapAuthConfig->searchBindDn();
            $searchBindPass = $this->ldapAuthConfig->searchBindPass();

            // add "realm" after user name if none is specified
            if (null !== $addRealm = $this->ldapAuthConfig->addRealm()) {
                if (false === strpos($authUser, '@')) {
                    $authUser .= '@'.$addRealm;
                }
            }

            if (null !== $bindDnTemplate = $this->ldapAuthConfig->bindDnTemplate()) {
                $userDn = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $bindDnTemplate);
                if (false !== ldap_explode_dn($userDn, 0)) {
                    // this is actually a valid LDAP DN, so use it
                    return $userDn;
                }

                // we have a "DN" that is probably for Active Directory of the
                // format EXAMPLE\user or user@example.org, we use the
                // credentials to figure out the user's own DN
                $searchBindDn = $userDn;
                $searchBindPass = $authPass;
            }

            // find DN based on userFilterTemplate
            $this->ldapClient->bind($searchBindDn, $searchBindPass);
            $userFilter = str_replace('{{UID}}', LdapClient::escapeFilter($authUser), $this->ldapAuthConfig->userFilterTemplate());

            foreach ($this->ldapAuthConfig->baseDn() as $baseDn) {
                // loop over all DNs
                if (null === $ldapResult = $this->ldapClient->search($baseDn, $userFilter)) {
                    continue;
                }

                return $ldapResult['dn'];
            }

            throw new CredentialValidatorException(sprintf('no such user "%s"', $authUser));
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
            if (null === $searchResult = $this->ldapClient->search($userDn, null, $attributeNameList, LdapClient::LDAP_SCOPE_BASE)) {
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
