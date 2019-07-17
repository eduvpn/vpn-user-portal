<?php

declare(strict_types=1);

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

class ADLdapAuth implements CredentialValidatorInterface
{
    const LDAP_MATCHING_RULE_IN_CHAIN_OID = '1.2.840.113556.1.4.1941';

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\Portal\LdapClient */
    private $ldapClient;

    /** @var string */
    private $bindDnTemplate;

    /** @var ?string */
    private $baseDn;

    /** @var array */
    private $permissionMemberships;

    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, string $bindDnTemplate, ?string $baseDn, array $permissionMemberships)
    {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->bindDnTemplate = $bindDnTemplate;
        $this->baseDn = $baseDn;
        $this->permissionMemberships = $permissionMemberships;
    }

    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        $bindDn = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->bindDnTemplate);
        try {
            $this->ldapClient->bind($bindDn, $authPass);

            $baseDn = $bindDn;
            if (null !== $this->baseDn) {
                $baseDn = $this->baseDn;
            }

            $permissions = $this->getPermissionList($baseDn, $bindDn);

            if (0 === \count($permissions)) {
                throw new LdapClientException('no required membership');
            }

            return new UserInfo($authUser, $permissions);
        } catch (LdapClientException $e) {
            $this->logger->warning(
                sprintf('unable to bind with DN "%s" (%s)', $bindDn, $e->getMessage())
            );

            return false;
        }
    }

    /**
     * @return array<string>
     */
    private function getPermissionList(string $baseDn, string $bindDn): array
    {
        $permissions = [];
        foreach ($this->permissionMemberships as $group => $perm) {
            $ldapEntries = $this->ldapClient->search(
                $baseDn,
                sprintf(
                    '(&(userPrincipalName=%s)(memberOf:%s:=%s))',
                    $bindDn,
                    self::LDAP_MATCHING_RULE_IN_CHAIN_OID,
                    LdapClient::escapeDn($group)
                ),
                []
            );

            if (0 < $ldapEntries['count']) {
                $permissions[] = $perm;
            }
        }

        return $permissions;
    }
}
