<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;
use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\OpenVpn\ServerManagerInterface;
use LC\Portal\Storage;
use LC\Portal\TplInterface;
use RuntimeException;

class AdminPortalModule implements ServiceModuleInterface
{
    /** @var string */
    private $dataDir;

    /** @var \LC\Portal\Config\PortalConfig */
    private $portalConfig;

    /** @var \LC\Portal\TplInterface */
    private $tpl;

    /** @var \LC\Portal\Storage */
    private $storage;

    /** @var \LC\Portal\OpenVpn\ServerManagerInterface */
    private $serverManager;

    /** @var \DateTime */
    private $dateTimeToday;

    public function __construct(string $dataDir, PortalConfig $portalConfig, TplInterface $tpl, Storage $storage, ServerManagerInterface $serverManager)
    {
        $this->dataDir = $dataDir;
        $this->portalConfig = $portalConfig;
        $this->tpl = $tpl;
        $this->storage = $storage;
        $this->serverManager = $serverManager;
        $this->dateTimeToday = new DateTime('today');
    }

    public function init(Service $service): void
    {
        $service->get(
            '/connections',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'profileConfigList' => $this->portalConfig->getProfileConfigList(),
                            'profileConnectionList' => $this->getProfileConnectionList(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'profileConfigList' => $this->portalConfig->getProfileConfigList(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                $userList = $this->storage->getUsers();

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserList',
                        [
                            'userList' => $userList,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/user',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $adminUserId = $userInfo->getUserId();
                $userId = InputValidation::userId($request->requireQueryParameter('user_id'));
                $clientCertificateList = $this->storage->getCertificates($userId);
                $userMessages = $this->storage->userMessages($userId);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserConfigList',
                        [
                            'userId' => $userId,
                            'userMessages' => $userMessages,
                            'clientCertificateList' => $clientCertificateList,
                            'hasTotpSecret' => false !== $this->storage->getOtpSecret($userId),
                            'isDisabled' => $this->storage->isDisabledUser($userId),
                            'isSelf' => $adminUserId === $userId, // the admin is viewing their own account
                        ]
                    )
                );
            }
        );

        $service->post(
            '/user',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);
                /** @var \LC\Portal\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $adminUserId = $userInfo->getUserId();
                $userId = InputValidation::userId($request->requirePostParameter('user_id'));

                // if the current user being managed is the account itself,
                // do not allow this. We don't want admins allow to disable
                // themselves or remove their own 2FA.
                if ($adminUserId === $userId) {
                    throw new HttpException('cannot manage own account', 400);
                }

                $userAction = $request->requirePostParameter('user_action');
                // no need to explicitly validate userAction, as we will have
                // switch below with whitelisted acceptable values

                switch ($userAction) {
                    case 'disableUser':
                        // get active connections for this user
                        $clientConnections = $this->getProfileConnectionList($userId);

                        // disable the user
                        $this->storage->disableUser($userId);
                        $this->storage->addUserMessage($userId, 'notification', 'account disabled');
                        // * revoke all OAuth clients of this user
                        // * delete all client certificates associated with the OAuth clients of this user
                        $clientAuthorizations = $this->storage->getAuthorizations($userId);
                        foreach ($clientAuthorizations as $clientAuthorization) {
                            $this->storage->deleteAuthorization($clientAuthorization['auth_key']);
                            $this->storage->deleteCertificatesOfClientId($userId, $clientAuthorization['client_id']);
                        }

                        // kill all active connections for this user
                        foreach ($clientConnections as $connectionList) {
                            foreach ($connectionList as $connection) {
                                $this->serverManager->kill($connection['common_name']);
                            }
                        }
                        break;

                    case 'enableUser':
                        $this->storage->enableUser($userId);
                        $this->storage->addUserMessage($userId, 'notification', 'account (re)enabled');
                        break;

                    case 'deleteTotpSecret':
                        $this->storage->deleteOtpSecret($userId);
                        $this->storage->addUserMessage($userId, 'notification', 'TOTP secret deleted');
                        break;

                    default:
                        throw new HttpException('unsupported "user_action"', 400);
                }

                $returnUrl = sprintf('%susers', $request->getRootUri());

                return new RedirectResponse($returnUrl);
            }
        );

        $service->get(
            '/log',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'currentDate' => date('Y-m-d H:i:s'),
                            'date_time' => null,
                            'ip_address' => null,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/stats',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'statsData' => $this->getStatsData(),
                            'graphStats' => $this->getGraphStats(),
                            'maxConcurrentConnectionLimit' => $this->getMaxConcurrentConnectionLimit(),
                            'profileConfigList' => $this->portalConfig->getProfileConfigList(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/messages',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                $motdMessages = $this->storage->systemMessages('motd');

                // we only want the first one
                if (0 === \count($motdMessages)) {
                    $motdMessage = false;
                } else {
                    $motdMessage = $motdMessages[0];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminMessages',
                        [
                            'motdMessage' => $motdMessage,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/messages',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                $messageAction = $request->requirePostParameter('message_action');
                switch ($messageAction) {
                    case 'set':
                        // we can only have one "motd", so remove the ones that
                        // already exist
                        $motdMessages = $this->storage->systemMessages('motd');
                        foreach ($motdMessages as $motdMessage) {
                            $this->storage->deleteSystemMessage($motdMessage['id']);
                        }

                        // no need to validate, we accept everything
                        $messageBody = InputValidation::systemMessage($request->requirePostParameter('message_body'));
                        $this->storage->addSystemMessage('motd', $messageBody);
                        break;
                    case 'delete':
                        $messageId = InputValidation::messageId($request->requirePostParameter('message_id'));
                        $this->storage->deleteSystemMessage($messageId);
                        break;
                    default:
                        throw new HttpException('unsupported "message_action"', 400);
                }

                $returnUrl = sprintf('%smessages', $request->getRootUri());

                return new RedirectResponse($returnUrl);
            }
        );

        $service->post(
            '/log',
            function (Request $request, array $hookData): Response {
                self::requireAdmin($hookData);

                $dateTime = InputValidation::dateTime($request->requirePostParameter('date_time'));
                $ipAddress = InputValidation::ipAddress($request->requirePostParameter('ip_address'));
                $logData = $this->storage->getLogEntry($dateTime, $ipAddress);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'currentDate' => date('Y-m-d H:i:s'),
                            'date_time' => $dateTime,
                            'ip_address' => $ipAddress,
                            'result' => $logData,
                        ]
                    )
                );
            }
        );
    }

    private function getMaxConcurrentConnectionLimit(): array
    {
        $maxConcurrentConnectionLimitList = [];
        foreach ($this->portalConfig->getProfileConfigList() as $profileId => $profileConfig) {
            list($ipFour, $ipFourPrefix) = explode('/', $profileConfig->getRangeFour());
            $vpnProtoPortsCount = \count($profileConfig->getVpnProtoPortList());
            $maxConcurrentConnectionLimitList[$profileId] = ((int) pow(2, 32 - (int) $ipFourPrefix)) - 3 * $vpnProtoPortsCount;
        }

        return $maxConcurrentConnectionLimitList;
    }

    private function getStatsData(): array
    {
        $statsFile = sprintf('%s/stats.json', $this->dataDir);
        try {
            return FileIO::readJsonFile($statsFile);
        } catch (RuntimeException $e) {
            // no stats file available (yet) or unable to read it
            return [];
        }
    }

    private function getGraphStats(): array
    {
        $outputData = [];
        $statsData = $this->getStatsData();
        foreach ($statsData as $profileId => $daysList) {
            $outputData[$profileId] = [];
            // find max number of unique users/traffic per day
            $maxUniqueUserCount = 0;
            $maxTrafficCount = 0;
            foreach ($daysList['days'] as $dayData) {
                if ($dayData['unique_user_count'] > $maxUniqueUserCount) {
                    $maxUniqueUserCount = $dayData['unique_user_count'];
                }
                if ($dayData['bytes_transferred'] > $maxTrafficCount) {
                    $maxTrafficCount = $dayData['bytes_transferred'];
                }
            }
            // convert users/traffic to a number between 0 and 25
            $maxUserDivider = $maxUniqueUserCount / 25;
            $maxTrafficDivider = $maxTrafficCount / 25;
            foreach ($daysList['days'] as $dayData) {
                $outputData[$profileId][$dayData['date']] = [
                    'user_fraction' => (int) floor($dayData['unique_user_count'] / $maxUserDivider),
                    'traffic_fraction' => (int) floor($dayData['bytes_transferred'] / $maxTrafficDivider),
                ];
            }
        }

        return $outputData;
    }

    private function getProfileConnectionList(?string $userId = null): array
    {
        $profileConnectionList = [];
        foreach (array_keys($this->portalConfig->getProfileConfigList()) as $profileId) {
            $profileConnectionList[$profileId] = [];
        }

        // XXX make sure all profile IDs are there in the getProfileConnectionList!
        // what if client connections are connected to no longer existing profiles?!
        foreach ($this->serverManager->connections() as $profileId => $clientConnectionList) {
            foreach ($clientConnectionList as $clientConnection) {
                if (false === $certInfo = $this->storage->getUserCertificateInfo($clientConnection['common_name'])) {
                    // this SHOULD never happen, it would mean that
                    // disconnecting the user when the certificate was deleted
                    // did not work, this is effectively a "ghost" connection,
                    // it will be automatically kicked offline the next time
                    // the cronjob that takes care of deleted / expired
                    // certificates runs...
                    // XXX write event to log!
                    continue;
                }

                // if requested, only return connections for a particular user_id
                if (null !== $userId) {
                    if ($certInfo['user_id'] !== $userId) {
                        continue;
                    }
                }

                $profileConnectionList[$profileId][] = array_merge($clientConnection, $certInfo);
            }
        }

        return $profileConnectionList;
    }

    private static function requireAdmin(array $hookData): void
    {
        if (false === $hookData['is_admin']) {
            throw new HttpException('user is not an administrator', 403);
        }
    }
}
