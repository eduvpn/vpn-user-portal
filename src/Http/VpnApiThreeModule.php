<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTimeImmutable;
use LC\Portal\CA\CaInterface;
use LC\Portal\ClientConfig;
use LC\Portal\Config;
use LC\Portal\Http\Exception\InputValidationException;
use LC\Portal\LoggerInterface;
use LC\Portal\OAuth\VpnAccessToken;
use LC\Portal\ProfileConfig;
use LC\Portal\RandomInterface;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use LC\Portal\WireGuard\WgConfig;
use LC\Portal\WireGuard\WgDaemon;

class VpnApiThreeModule implements ApiServiceModuleInterface
{
    private Config $config;
    private Storage $storage;
    private TlsCrypt $tlsCrypt;
    private RandomInterface $random;
    private CaInterface $ca;
    private WgDaemon $wgDaemon;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, Storage $storage, TlsCrypt $tlsCrypt, RandomInterface $random, CaInterface $ca, WgDaemon $wgDaemon)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->tlsCrypt = $tlsCrypt;
        $this->random = $random;
        $this->ca = $ca;
        $this->wgDaemon = $wgDaemon;
        $this->dateTime = new DateTimeImmutable();
    }

    public function init(ApiService $service): void
    {
        $service->get(
            '/v3/info',
            function (VpnAccessToken $accessToken, Request $request): Response {
                $profileList = $this->profileList();
                // XXX really think about storing permissions in OAuth token!
                $userPermissions = $this->getPermissionList($accessToken);
                $userProfileList = [];
                foreach ($profileList as $profileId => $profileConfig) {
                    if ($profileConfig->hideProfile()) {
                        continue;
                    }
                    if ($profileConfig->enableAcl()) {
                        // is the user member of the aclPermissionList?
                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                            continue;
                        }
                    }
                    $userProfileList[] = [
                        'profile_id' => $profileId,
                        'display_name' => $profileConfig->displayName(),
                    ];
                }

                return new JsonResponse(
                    [
                        'info' => [
                            'profile_list' => $userProfileList,
                        ],
                    ]
                );
            }
        );

        $service->post(
            '/v3/connect',
            function (VpnAccessToken $accessToken, Request $request): Response {
                // XXX catch InputValidationException
                $requestedProfileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
                $profileList = $this->profileList();
                $userPermissions = $this->getPermissionList($accessToken);
                $availableProfiles = [];
                foreach ($profileList as $profileId => $profileConfig) {
                    if ($profileConfig->hideProfile()) {
                        continue;
                    }
                    if ($profileConfig->enableAcl()) {
                        // is the user member of the userPermissions?
                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
                            continue;
                        }
                    }

                    $availableProfiles[] = $profileId;
                }

                if (!\in_array($requestedProfileId, $availableProfiles, true)) {
                    return new JsonResponse(['error' => 'profile not available or no permission'], [], 400);
                }

                $profileConfig = $profileList[$requestedProfileId];

                switch ($profileConfig->vpnType()) {
                    case 'openvpn':
                        return $this->getOpenVpnConfigResponse($profileConfig, $accessToken, $requestedProfileId);
                    case 'wireguard':
                        return $this->getWireGuardConfigResponse($profileConfig, $accessToken, $requestedProfileId);
                    default:
                        return new JsonResponse(['error' => 'invalid vpn_type'], [], 500);
                }
            }
        );

        $service->post(
            '/v3/disconnect',
            function (VpnAccessToken $accessToken, Request $request): Response {
//                // XXX catch InputValidationException
//                $requestedProfileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
//                $profileList = $this->profileList();
//                $userPermissions = $this->getPermissionList($accessToken);
//                $availableProfiles = [];
//                foreach ($profileList as $profileId => $profileConfig) {
//                    if ($profileConfig->hideProfile()) {
//                        continue;
//                    }
//                    if ($profileConfig->enableAcl()) {
//                        // is the user member of the userPermissions?
//                        if (!VpnPortalModule::isMember($profileConfig->aclPermissionList(), $userPermissions)) {
//                            continue;
//                        }
//                    }

//                    $availableProfiles[] = $profileId;
//                }

//                if (!\in_array($requestedProfileId, $availableProfiles, true)) {
//                    return new JsonResponse(['error' => 'profile not available or no permission'], [], 400);
//                }

//                $profileConfig = $profileList[$requestedProfileId];

//                if('wireguard' !== $profileConfig->vpnType()) {
//                    return new Response('', [], 204);
//                }
//
//                // remove peer and remove from DB?
//                $wgDevice = 'wg'.($profileConfig->profileNumber() - 1);
//
//                // we have to find the public key of this user for this
//                // profile...what if there are multiple wg connections for 1
//                // user to 1 profile? Meh!
//                // XXX hwo to do this? require passing public key?
//                // we'd only have to check it belongs to the user... this is
//                // neat in the case of providing a public key on connect, can
//                // provide the same on disconnect...
//
//                // XXX also remove from DB
//
//                $this->wgDaemon->removePeer($wgDevice, string $publicKey): void

                return new Response('', [], 204);
            }
        );
    }

    private function getWireGuardConfigResponse(ProfileConfig $profileConfig, VpnAccessToken $accessToken, string $profileId): Response
    {
        $privateKey = self::generatePrivateKey();
        $publicKey = self::generatePublicKey($privateKey);
        if (null === $ipInfo = $this->getIpAddress($profileConfig)) {
            // unable to get new IP address to assign to peer
            return new JsonResponse(['unable to get a an IP address'], [], 500);
        }
        [$ipFour, $ipSix] = $ipInfo;

        // store peer in the DB
        $this->storage->wgAddPeer($accessToken->getUserId(), $profileId, $accessToken->accessToken()->clientId(), $publicKey, $ipFour, $ipSix, $this->dateTime, null);

        $wgDevice = 'wg'.($profileConfig->profileNumber() - 1);

        // add peer to WG
        $this->wgDaemon->addPeer($wgDevice, $publicKey, $ipFour, $ipSix);

        $wgInfo = $this->wgDaemon->getInfo($wgDevice);

        $wgConfig = new WgConfig(
            $publicKey,
            $ipFour,
            $ipSix,
            $wgInfo['PublicKey'],
            $profileConfig->hostName(),
            $wgInfo['ListenPort'],
            $profileConfig->dns(),
            $privateKey
        );

        return new Response((string) $wgConfig, ['Content-Type' => 'application/x-wireguard-profile']);
    }

    private static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }

    private static function generatePublicKey(string $privateKey): string
    {
        ob_start();
        passthru("echo $privateKey | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }

    /**
     * @param string $ipAddressPrefix
     *
     * @return array<string>
     */
    private static function getIpInRangeList($ipAddressPrefix)
    {
        [$ipAddress, $ipPrefix] = explode('/', $ipAddressPrefix);
        $ipPrefix = (int) $ipPrefix;
        $ipNetmask = long2ip(-1 << (32 - $ipPrefix));
        $ipNetwork = long2ip(ip2long($ipAddress) & ip2long($ipNetmask));
        $numberOfHosts = (int) 2 ** (32 - $ipPrefix) - 2;
        if ($ipPrefix > 30) {
            return [];
        }
        $hostList = [];
        for ($i = 2; $i <= $numberOfHosts; ++$i) {
            $hostList[] = long2ip(ip2long($ipNetwork) + $i);
        }

        return $hostList;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function getIpAddress(ProfileConfig $profileConfig)
    {
        // make a list of all allocated IPv4 addresses (the IPv6 address is
        // based on the IPv4 address)
        $allocatedIpFourList = $this->storage->wgGetAllocatedIpFourAddresses();
        $ipInRangeList = self::getIpInRangeList($profileConfig->range());
        foreach ($ipInRangeList as $ipInRange) {
            if (!\in_array($ipInRange, $allocatedIpFourList, true)) {
                // include this IPv4 address in IPv6 address
                [$ipSixAddress, $ipSixPrefix] = explode('/', $profileConfig->range6());
                $ipSixPrefix = (int) $ipSixPrefix;
                $ipFourHex = bin2hex(inet_pton($ipInRange));
                $ipSixHex = bin2hex(inet_pton($ipSixAddress));
                // clear the last $ipSixPrefix/4 elements
                $ipSixHex = substr_replace($ipSixHex, str_repeat('0', (int) ($ipSixPrefix / 4)), -((int) ($ipSixPrefix / 4)));
                $ipSixHex = substr_replace($ipSixHex, $ipFourHex, -8);
                $ipSix = inet_ntop(hex2bin($ipSixHex));

                return [$ipInRange, $ipSix];
            }
        }

        return null;
    }

    private function getOpenVpnConfigResponse(ProfileConfig $profileConfig, VpnAccessToken $accessToken, string $profileId): Response
    {
        $commonName = $this->random->get(16);
        $certInfo = $this->ca->clientCert($commonName, $profileId, $accessToken->accessToken()->authorizationExpiresAt());
        // XXX also store profile_id in DB
        $this->storage->addCertificate(
            $accessToken->getUserId(),
            $commonName,
            $accessToken->accessToken()->clientId(),
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_from'])),
            new DateTimeImmutable(sprintf('@%d', $certInfo['valid_to'])),
            $accessToken->accessToken()->clientId()
        );

        $this->storage->addUserLog(
            $accessToken->getUserId(),
            LoggerInterface::NOTICE,
            sprintf('new certificate generated for "%s"', $accessToken->accessToken()->clientId()),
            $this->dateTime
        );

        // get the CA & tls-crypt
        $serverInfo = [
            'tls_crypt' => $this->tlsCrypt->get($profileId),
            'ca' => $this->ca->caCert(),
        ];

        $clientConfig = ClientConfig::get(
            $profileConfig,
            $serverInfo,
            $certInfo,
            ClientConfig::STRATEGY_RANDOM
        );

        return new Response($clientConfig, ['Content-Type' => 'application/x-openvpn-profile']);
    }

    /**
     * @return array<string>
     */
    private function getPermissionList(VpnAccessToken $vpnAccessToken): array
    {
        if (!$vpnAccessToken->isLocal()) {
            return [];
        }

        return $this->storage->getPermissionList($vpnAccessToken->getUserId());
    }

    /**
     * XXX duplicate in AdminPortalModule|VpnPortalModule.
     *
     * @return array<string,\LC\Portal\ProfileConfig>
     */
    private function profileList(): array
    {
        $profileList = [];
        foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
            $profileConfig = new ProfileConfig(new Config($profileData));
            $profileList[$profileId] = $profileConfig;
        }

        return $profileList;
    }
}
