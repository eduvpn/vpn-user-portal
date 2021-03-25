<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use LC\Portal\Http\Exception\RadiusException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RadiusAuth implements CredentialValidatorInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var array */
    private $serverList;

    /** @var string|null */
    private $realm = null;

    /** @var string|null */
    private $nasIdentifier = null;

    public function __construct(LoggerInterface $logger, array $serverList)
    {
        if (false === \extension_loaded('radius')) {
            throw new RuntimeException('"radius" PHP extension not available');
        }
        $this->logger = $logger;
        $this->serverList = $serverList;
    }

    public function setRealm(string $realm): void
    {
        $this->realm = $realm;
    }

    public function setNasIdentifier(string $nasIdentifier): void
    {
        $this->nasIdentifier = $nasIdentifier;
    }

    /**
     * @return false|UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        // add realm if requested
        if (null !== $this->realm) {
            $authUser = sprintf('%s@%s', $authUser, $this->realm);
        }

        $radiusAuth = radius_auth_open();

        foreach ($this->serverList as $radiusServer) {
            if (false === radius_add_server(
                $radiusAuth,
                $radiusServer['host'],
                \array_key_exists('port', $radiusServer) ? $radiusServer['port'] : 1812,
                $radiusServer['secret'],
                5,  // timeout
                3   // max_tries
            )) {
                $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
                $this->logger->error($errorMsg);

                throw new RadiusException($errorMsg);
            }
        }

        if (false === radius_create_request($radiusAuth, \RADIUS_ACCESS_REQUEST)) {
            $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
            $this->logger->error($errorMsg);

            throw new RadiusException($errorMsg);
        }

        radius_put_attr($radiusAuth, \RADIUS_USER_NAME, $authUser);
        radius_put_attr($radiusAuth, \RADIUS_USER_PASSWORD, $authPass);
        if (null !== $this->nasIdentifier) {
            radius_put_attr($radiusAuth, \RADIUS_NAS_IDENTIFIER, $this->nasIdentifier);
        }

        if (\RADIUS_ACCESS_ACCEPT === radius_send_request($radiusAuth)) {
            return new UserInfo($authUser, []);
        }

        if (\RADIUS_ACCESS_REJECT === radius_send_request($radiusAuth)) {
            // wrong authUser/authPass
            return false;
        }

        $errorMsg = sprintf('RADIUS error: %s', radius_strerror($radiusAuth));
        $this->logger->error($errorMsg);

        throw new RadiusException($errorMsg);
    }
}
