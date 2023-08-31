<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http\Auth;

use RuntimeException;
use Vpn\Portal\Cfg\RadiusAuthConfig;
use Vpn\Portal\Http\Auth\Exception\CredentialValidatorException;
use Vpn\Portal\Http\Auth\Exception\RadiusException;
use Vpn\Portal\Http\UserInfo;
use Vpn\Portal\LoggerInterface;

/**
 * @psalm-suppress UndefinedConstant
 */
class RadiusCredentialValidator implements CredentialValidatorInterface
{
    private LoggerInterface $logger;
    private RadiusAuthConfig $radiusAuthConfig;

    public function __construct(LoggerInterface $logger, RadiusAuthConfig $radiusAuthConfig)
    {
        if (false === \extension_loaded('radius')) {
            throw new RuntimeException('"radius" PHP extension not available');
        }
        $this->logger = $logger;
        $this->radiusAuthConfig = $radiusAuthConfig;
    }

    /**
     * @throws \Vpn\Portal\Http\Auth\Exception\CredentialValidatorException
     */
    public function validate(string $authUser, string $authPass): UserInfo
    {
        // make sure authUser does not contain "\x00" string
        if (false !== strpos($authUser, "\x00")) {
            throw new CredentialValidatorException('"authUser" contains "\x00"');
        }

        // add realm when requested
        if (null !== $radiusRealm = $this->radiusAuthConfig->radiusRealm()) {
            $authUser = sprintf('%s@%s', $authUser, $radiusRealm);
        }

        $radiusAuth = radius_auth_open();
        foreach ($this->radiusAuthConfig->serverList() as $radiusServer) {
            $radiusInfo = explode(':', $radiusServer, 3);
            if (3 !== count($radiusInfo)) {
                throw new RadiusException('invalid RADIUS `serverList` entry, format: `host:port:secret`');
            }
            [$radiusHost, $radiusPort, $radiusSecret] = $radiusInfo;
            if (false === radius_add_server(
                $radiusAuth,
                $radiusHost,
                (int) $radiusPort,
                $radiusSecret,
                5,  // timeout
                3   // max_tries
            )) {
                $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
                $this->logger->error($errorMsg);

                throw new RadiusException($errorMsg);
            }
        }

        if (false === radius_create_request($radiusAuth, RADIUS_ACCESS_REQUEST)) {
            $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
            $this->logger->error($errorMsg);

            throw new RadiusException($errorMsg);
        }

        radius_put_attr($radiusAuth, RADIUS_USER_NAME, $authUser);
        radius_put_attr($radiusAuth, RADIUS_USER_PASSWORD, $authPass);
        if (null !== $nasIdentifier = $this->radiusAuthConfig->nasIdentifier()) {
            radius_put_attr($radiusAuth, RADIUS_NAS_IDENTIFIER, $nasIdentifier);
        }

        $radiusResponse = radius_send_request($radiusAuth);
        if (false === $radiusResponse) {
            $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
            $this->logger->error($errorMsg);

            throw new RadiusException($errorMsg);
        }

        if (RADIUS_ACCESS_ACCEPT !== $radiusResponse) {
            // most likely wrong authUser/authPass, not necessarily an error
            throw new CredentialValidatorException('authentication not accepted');
        }

        $permissionList = [];
        if (null !== $permissionAttribute = $this->radiusAuthConfig->permissionAttribute()) {
            // find the authorization attribute and use its value
            while ($radiusAttribute = radius_get_attr($radiusAuth)) {
                if (!\is_array($radiusAttribute)) {
                    continue;
                }
                if ($permissionAttribute !== $radiusAttribute['attr']) {
                    continue;
                }
                $permissionList[(string) $permissionAttribute] = $radiusAttribute['data'];
            }
        }

        return new UserInfo(
            $authUser,
            AbstractAuthModule::flattenPermissionList($permissionList)
        );
    }
}
