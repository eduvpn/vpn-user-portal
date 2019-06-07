<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Config\RadiusAuthenticationConfig;
use LC\Portal\Http\Exception\RadiusException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RadiusAuth implements CredentialValidatorInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\Portal\Config\RadiusAuthenticationConfig */
    private $radiusAuthenticationConfig;

    public function __construct(LoggerInterface $logger, RadiusAuthenticationConfig $radiusAuthenticationConfig)
    {
        if (false === \extension_loaded('radius')) {
            throw new RuntimeException('"radius" PHP extension not available');
        }
        $this->logger = $logger;
        $this->radiusAuthenticationConfig = $radiusAuthenticationConfig;
    }

    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        // add realm if requested
        if (null !== $realm = $this->radiusAuthenticationConfig->getRealm()) {
            $authUser = sprintf('%s@%s', $authUser, $realm);
        }

        $radiusAuth = radius_auth_open();

        foreach ($this->radiusAuthenticationConfig->getServerList() as $radiusServerConfig) {
            if (false === radius_add_server(
                $radiusAuth,
                $radiusServerConfig->getHost(),
                $radiusServerConfig->getPort(),
                $radiusServerConfig->getSecret(),
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
        if (null !== $nasIdentifier = $this->radiusAuthenticationConfig->getNasIdentifier()) {
            radius_put_attr($radiusAuth, RADIUS_NAS_IDENTIFIER, $nasIdentifier);
        }

        if (RADIUS_ACCESS_ACCEPT === radius_send_request($radiusAuth)) {
            return new UserInfo($authUser, []);
        }

        if (RADIUS_ACCESS_REJECT === radius_send_request($radiusAuth)) {
            // wrong authUser/authPass
            return false;
        }

        $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
        $this->logger->error($errorMsg);

        throw new RadiusException($errorMsg);
    }
}
