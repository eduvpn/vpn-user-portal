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
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
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
     * @param array<string> $attributeList
     *
     * @return array{dn:string,result:array<string,array<string>>}
     */
    public function search(string $baseDn, ?string $searchFilter, array $attributeList = []): array
    {
        // for efficienty purposes, if the of requested attributes is empty,
        // we simply request 'dn', even though it is always part of the
        // response... if we do not request anything, ldap_search will return
        // *all* attributes/values
        if (0 === count($attributeList)) {
            $attributeList = ['dn'];
        }
        // make sure we request the same attribute not >1
        // (this should be case *in*sensitive, but well...
        $attributeList = array_values(array_unique($attributeList));

        $searchResource = ldap_search(
            $this->ldapResource,                // link_identifier
            $baseDn,                            // base_dn
            $searchFilter ?? '(objectClass=*)', // filter
            $attributeList,                     // attributes (dn is always returned...)
            0,                                  // attrsonly
            0,                                  // sizelimit
            10                                  // timelimit
        );
        if (false === $searchResource) {
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }
        if (is_array($searchResource)) {
            // ldap_search can return array when doing parallel search, as we
            // don't do that this should not occur, but just making sure and
            // to silence vimeo/psalm
            // @see https://www.php.net/ldap_search
            throw new LdapClientException('multiple results returned, expecting only one');
        }

        $ldapEntries = ldap_get_entries($this->ldapResource, $searchResource);
        if (false === $ldapEntries) {
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }

        // parse the results and return them in a format we can easily use
        if (!isset($ldapEntries['count']) || 1 !== $ldapEntries['count']) {
            throw new LdapClientException(sprintf('expected exactly 1 result, not less, not more, we got "%d"', $ldapEntries['count']));
        }

        if (!isset($ldapEntries[0]['dn']) || !is_string($ldapEntries[0]['dn'])) {
            throw new LdapClientException('no "dn" in the result or not `string`');
        }

        $resultData = [
            'dn' => $ldapEntries[0]['dn'],
        ];

        $attributeNameValues = [];
        foreach ($attributeList as $attributeName) {
            if ('dn' === $attributeName) {
                // we always have dn (see above), so if this is (also)
                // explicitly requested we ignore it
                continue;
            }

            if (isset($ldapEntries[0][strtolower($attributeName)][0])) {
                if (!array_key_exists($attributeName, $attributeNameValues)) {
                    $attributeNameValues[$attributeName] = [];
                }
                $attributeNameValues[$attributeName] = array_merge($attributeNameValues[$attributeName], array_slice($ldapEntries[0][strtolower($attributeName)], 1));
            }
        }
        $resultData['result'] = $attributeNameValues;

        return $resultData;
    }
}
