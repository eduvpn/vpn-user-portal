<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use RuntimeException;
use UnexpectedValueException;
use Vpn\Portal\Exception\LdapClientException;

class LdapClient
{
    /** @var resource */
    private $ldapResource;

    public function __construct(string $ldapUri, ?string $tlsCa = null, ?string $tlsCert = null, ?string $tlsKey = null)
    {
        if (!extension_loaded('ldap')) {
            throw new RuntimeException('"ldap" PHP extension not available');
        }

        $ldapOptions = [
            LDAP_OPT_PROTOCOL_VERSION => 3,
            LDAP_OPT_REFERRALS => 0,
        ];

        if (null !== $tlsCa) {
            $ldapOptions[LDAP_OPT_X_TLS_CACERTFILE] = $tlsCa;
        }
        if (null !== $tlsCert) {
            $ldapOptions[LDAP_OPT_X_TLS_CERTFILE] = $tlsCert;
        }
        if (null !== $tlsKey) {
            $ldapOptions[LDAP_OPT_X_TLS_KEYFILE] = $tlsKey;
        }

        foreach ($ldapOptions as $k => $v) {
            if (!ldap_set_option(null, $k, $v)) {
                throw new LdapClientException(sprintf('unable to set LDAP option "%d"', $k));
            }
        }

        if (false === $ldapResource = ldap_connect($ldapUri)) {
            throw new LdapClientException(sprintf('invalid LDAP URI "%s"', $ldapUri));
        }

        $this->ldapResource = $ldapResource;
    }

    public function __destruct()
    {
        ldap_unbind($this->ldapResource);
    }

    public function bind(?string $bindUser = null, ?string $bindPass = null): void
    {
        if (!ldap_bind($this->ldapResource, $bindUser, $bindPass)) {
            throw new LdapClientException(sprintf('ldap_bind (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }
    }

    public static function escapeDn(string $inputStr): string
    {
        return ldap_escape($inputStr, '', LDAP_ESCAPE_DN);
    }

    public static function escapeFilter(string $inputStr): string
    {
        return ldap_escape($inputStr, '', LDAP_ESCAPE_FILTER);
    }

    /**
     * Search the LDAP.
     *
     * Returns `null` when no DN matches the search filter. If more than one
     * DN matches, an exception is thrown.
     *
     * @param array<string> $attributeList
     *
     * @return ?array{dn:string,result:array<string,array<string>>}
     */
    public function search(string $baseDn, ?string $searchFilter, array $attributeList = []): ?array
    {
        // if no attributes are requested, explicitly request "dn", otherwise
        // all attributes/values are returned
        if (0 === count($attributeList)) {
            $attributeList = ['dn'];
        }

        $searchResource = ldap_search($this->ldapResource, $baseDn, $searchFilter ?? '(objectClass=*)', $attributeList, 0, 0, 10);
        if (false === $searchResource) {
            throw new LdapClientException(sprintf('ldap_search (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }
        if (is_array($searchResource)) {
            // ldap_search can return an array when doing parallel search, we
            // don't do that so this should never occur
            throw new UnexpectedValueException('ldap_search returned `array`, expected `LDAP\Result|resource`');
        }

        if (0 === $resultCount = ldap_count_entries($this->ldapResource, $searchResource)) {
            // no entries match our search
            return null;
        }

        if (1 !== $resultCount) {
            throw new LdapClientException(sprintf('we got %d results, base "%s" and filter "%s" probably not specific enough', $resultCount, $baseDn, $searchFilter ?? '(objectClass=*)'));
        }

        if (false === $ldapEntry = ldap_first_entry($this->ldapResource, $searchResource)) {
            // TODO: does failing ldap_first_entry actually generate "LDAP errors"?
            throw new LdapClientException(sprintf('ldap_first_entry (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }

        if (false === $entryDn = ldap_get_dn($this->ldapResource, $ldapEntry)) {
            // TODO: does failing ldap_get_dn actually generate "LDAP errors"?
            throw new LdapClientException(sprintf('ldap_get_dn (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }

        $attributeNameValues = [];
        foreach ($attributeList as $attributeName) {
            if ('dn' === $attributeName) {
                continue;
            }
            if (false === $attributeValues = ldap_get_values($this->ldapResource, $ldapEntry, $attributeName)) {
                // we do not have this attribute
                continue;
            }
            unset($attributeValues['count']);
            $attributeNameValues[$attributeName] = array_values($attributeValues);
        }

        return [
            'dn' => $entryDn,
            'result' => $attributeNameValues,
        ];
    }
}
