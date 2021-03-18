<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\LdapClientException;
use RuntimeException;

class LdapClient
{
    /** @var resource */
    private $ldapResource;

    /**
     * @param string $ldapUri
     */
    public function __construct($ldapUri)
    {
        if (false === \extension_loaded('ldap')) {
            throw new RuntimeException('"ldap" PHP extension not available');
        }
        $this->ldapResource = ldap_connect($ldapUri);
        if (false === $this->ldapResource) {
            // only with very old OpenLDAP will it ever return false...
            throw new LdapClientException(sprintf('unacceptable LDAP URI "%s"', $ldapUri));
        }
        if (false === ldap_set_option($this->ldapResource, \LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new LdapClientException('unable to set LDAP option');
        }
        if (false === ldap_set_option($this->ldapResource, \LDAP_OPT_REFERRALS, 0)) {
            throw new LdapClientException('unable to set LDAP option');
        }
    }

    /**
     * Bind to an LDAP server.
     *
     * @param string|null $bindUser you MUST use LdapClient::escapeDn on any user input used to contruct the DN!
     * @param string|null $bindPass
     *
     * @return void
     */
    public function bind($bindUser = null, $bindPass = null)
    {
        if (false === ldap_bind($this->ldapResource, $bindUser, $bindPass)) {
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public static function escapeDn($str)
    {
        // ldap_escape in PHP >= 5.6 (or symfony/polyfill-php56)
        return ldap_escape($str, '', \LDAP_ESCAPE_DN);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public static function escapeFilter($str)
    {
        // ldap_escape in PHP >= 5.6 (or symfony/polyfill-php56)
        return ldap_escape($str, '', \LDAP_ESCAPE_FILTER);
    }

    /**
     * @param string        $baseDn
     * @param string        $searchFilter
     * @param array<string> $attributeList
     *
     * @return array
     */
    public function search($baseDn, $searchFilter, array $attributeList = [])
    {
        $searchResource = ldap_search(
            $this->ldapResource,    // link_identifier
            $baseDn,                // base_dn
            $searchFilter,          // filter
            $attributeList,         // attributes (dn is always returned...)
            0,                      // attrsonly
            0,                      // sizelimit
            10                      // timelimit
        );
        if (false === $searchResource) {
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }

        $ldapEntries = ldap_get_entries($this->ldapResource, $searchResource);
        if (false === $ldapEntries) {
            throw new LdapClientException(sprintf('LDAP error: (%d) %s', ldap_errno($this->ldapResource), ldap_error($this->ldapResource)));
        }

        return $ldapEntries;
    }
}
