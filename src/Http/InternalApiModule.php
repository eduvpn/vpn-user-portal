<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;
use LC\Portal\CA\CaInterface;
use LC\Portal\Config;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\TlsAuth;

class InternalApiModule implements ServiceModuleInterface
{
    /** @var \LC\Portal\CA\CaInterface */
    private $ca;

    /** @var \LC\Portal\TlsAuth */
    private $tlsAuth;

    /** @var \LC\Portal\Config */
    private $config;

    /** @var \LC\Portal\Storage */
    private $storage;

    public function __construct(CaInterface $ca, TlsAuth $tlsAuth, Config $config, Storage $storage)
    {
        $this->ca = $ca;
        $this->tlsAuth = $tlsAuth;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/connect',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->connect($request);
            }
        );

        $service->post(
            '/disconnect',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->disconnect($request);
            }
        );

        $service->post(
            '/add_server_certificate',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                $commonName = InputValidation::serverCommonName($request->getPostParameter('common_name'));

                $certInfo = $this->ca->serverCert($commonName);
                // add TLS Auth
                $certInfo['ta'] = $this->tlsAuth->get();
                $certInfo['ca'] = $this->ca->caCert();

                return new ApiResponse('add_server_certificate', $certInfo, 201);
            }
        );

        $service->get(
            '/profile_list',
            /**
             * @return \LC\Portal\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-server-node']);

                $profileList = [];
                foreach ($this->config->getSection('vpnProfiles')->toArray() as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    $profileConfigArray = $profileConfig->toArray();
                    ksort($profileConfigArray);
                    $profileList[$profileId] = $profileConfigArray;
                }

                return new ApiResponse('profile_list', $profileList);
            }
        );
    }

    /**
     * @return \LC\Portal\Http\Response
     */
    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));

        if (null !== $response = $this->verifyConnection($profileId, $commonName)) {
            return $response;
        }

        $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)));

        return new ApiResponse('connect');
    }

    /**
     * @return \LC\Portal\Http\Response
     */
    public function disconnect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));

        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));
        $disconnectedAt = InputValidation::disconnectedAt($request->getPostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->getPostParameter('bytes_transferred'));

        $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)), new DateTime(sprintf('@%d', $disconnectedAt)), $bytesTransferred);

        return new ApiResponse('disconnect');
    }

    /**
     * @param string $profileId
     * @param string $commonName
     *
     * @return \LC\Portal\Http\ApiErrorResponse|null
     */
    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $result = $this->storage->getUserCertificateInfo($commonName)) {
            // if a certificate does no longer exist, we cannot figure out the user
            return new ApiErrorResponse('connect', 'user or certificate does not exist');
        }

        // XXX should we check whether or not session is expired yet?!

        if ($result['user_is_disabled']) {
            $msg = '[VPN] unable to connect, account is disabled';
            $this->storage->addUserMessage($result['user_id'], 'notification', $msg);

            return new ApiErrorResponse('connect', $msg);
        }

        return $this->verifyAcl($profileId, $result['user_id']);
    }

    /**
     * @param string $profileId
     * @param string $externalUserId
     *
     * @return \LC\Portal\Http\ApiErrorResponse|null
     */
    private function verifyAcl($profileId, $externalUserId)
    {
        // verify ACL
        $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
        if ($profileConfig->getItem('enableAcl')) {
            // ACL enabled
            $userPermissionList = $this->storage->getPermissionList($externalUserId);
            if (false === self::hasPermission($userPermissionList, $profileConfig->getSection('aclPermissionList')->toArray())) {
                $msg = '[VPN] unable to connect, user does not have required permissions';
                $this->storage->addUserMessage($externalUserId, 'notification', $msg);

                return new ApiErrorResponse('connect', $msg);
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    private static function hasPermission(array $userPermissionList, array $aclPermissionList)
    {
        // one of the permissions must be listed in the profile ACL list
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
