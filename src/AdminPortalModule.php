<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\ProfileConfig;
use LC\Common\TplInterface;
use LC\Portal\OpenVpn\ServerManager;
use RuntimeException;

class AdminPortalModule implements ServiceModuleInterface
{
    /** @var string */
    private $dataDir;

    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var Storage */
    private $storage;

    /** @var Graph */
    private $graph;

    /** @var \LC\Portal\OpenVpn\ServerManager */
    private $serverManager;

    /** @var \DateTime */
    private $dateTimeToday;

    /**
     * @param string                           $dataDir
     * @param \LC\Common\Config                $config
     * @param \LC\Common\TplInterface          $tpl
     * @param Storage                          $storage
     * @param \LC\Portal\OpenVpn\ServerManager $serverManager
     * @param Graph                            $graph
     */
    public function __construct($dataDir, Config $config, TplInterface $tpl, Storage $storage, ServerManager $serverManager, Graph $graph)
    {
        $this->dataDir = $dataDir;
        $this->config = $config;
        $this->tpl = $tpl;
        $this->storage = $storage;
        $this->serverManager = $serverManager;
        $this->graph = $graph;
        $this->dateTimeToday = new DateTime('today');
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/connections',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                // get the fancy profile name
                $profileList = $this->getProfileList();

                $idNameMapping = [];
                $profileConnectionList = [];
                foreach ($profileList as $profileId => $profileData) {
                    $idNameMapping[$profileId] = $profileData['displayName'];
                    $profileConnectionList[$profileId] = [];
                }

                // here we need to "enrich" the clientConnectionList, i.e. add
                // the user_id and display_name to the list
                $clientConnectionProfileList = $this->serverManager->connections();
                foreach ($clientConnectionProfileList as $clientConnectionList) {
                    foreach ($clientConnectionList['connections'] as $clientConnection) {
                        if (false === $certInfo = $this->storage->getUserCertificateInfo($clientConnection['common_name'])) {
                            // XXX come up with a way to show connections where the certificate was already deleted...
                            continue;
                        }
                        $profileId = $clientConnectionList['id'];
                        $profileConnectionList[$profileId][] = array_merge($clientConnection, $certInfo);
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'idNameMapping' => $idNameMapping,
                            'profileConnectionList' => $profileConnectionList,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'profileList' => $this->getProfileList(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $adminUserId = $userInfo->getUserId();
                $userId = $request->getQueryParameter('user_id');
                InputValidation::userId($userId);

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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);
                /** @var \LC\Common\Http\UserInfo */
                $userInfo = $hookData['auth'];
                $adminUserId = $userInfo->getUserId();
                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                // if the current user being managed is the account itself,
                // do not allow this. We don't want admins allow to disable
                // themselves or remove their own 2FA.
                if ($adminUserId === $userId) {
                    throw new HttpException('cannot manage own account', 400);
                }

                $userAction = $request->getPostParameter('user_action');
                // no need to explicitly validate userAction, as we will have
                // switch below with whitelisted acceptable values

                switch ($userAction) {
                    case 'disableUser':
                        // get active connections for this user
                        $clientConnections = $this->serverClient->getRequireArray('client_connections', ['user_id' => $userId]);

                        // disable the user
                        $this->storage->disableUser($userId);
                        $this->storage->addUserMessage($userId, 'notification', 'account disabled');
                        // * revoke all OAuth clients of this user
                        // * delete all client certificates associated with the OAuth clients of this user
                        $clientAuthorizations = $this->storage->getAuthorizations($userId);
                        foreach ($clientAuthorizations as $clientAuthorization) {
                            $this->storage->deleteAuthorization($clientAuthorization['auth_key']);
                            $this->serverClient->post('delete_client_certificates_of_client_id', ['user_id' => $userId, 'client_id' => $clientAuthorization['client_id']]);
                        }

                        // kill all active connections for this user
                        foreach ($clientConnections as $profile) {
                            foreach ($profile['connections'] as $connection) {
                                $this->serverClient->post('kill_client', ['common_name' => $connection['common_name']]);
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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $stats = $this->getStats();
                if (!\is_array($stats) || !\array_key_exists('profiles', $stats)) {
                    // this is an old "stats" format we no longer support,
                    // vpn-server-api-stats has to run again first, which is
                    // done by the crontab running at midnight...
                    $stats = false;
                }
                // get the fancy profile name
                $profileList = $this->getProfileList();

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileData) {
                    $idNameMapping[$profileId] = $profileData['displayName'];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'stats' => $stats,
                            'generated_at' => false !== $stats ? $stats['generated_at'] : false,
                            'generated_at_tz' => date('T'),
                            'idNameMapping' => $idNameMapping,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/stats/traffic',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $profileId = InputValidation::profileId($request->getQueryParameter('profile_id'));
                $response = new Response(
                    200,
                    'image/png'
                );

                if (false === $stats = $this->getStats()) {
                    throw new HttpException('no stats available', 400);
                }
                $dateByteList = [];
                foreach ($stats['profiles'][$profileId]['days'] as $v) {
                    $dateByteList[$v['date']] = $v['bytes_transferred'];
                }

                $imageData = $this->graph->draw(
                    $dateByteList,
                    /**
                     * @param int $v
                     *
                     * @return string
                     */
                    function ($v) {
                        $suffix = 'B';
                        if ($v > 1024) {
                            $v /= 1024;
                            $suffix = 'kiB';
                        }
                        if ($v > 1024) {
                            $v /= 1024;
                            $suffix = 'MiB';
                        }
                        if ($v > 1024) {
                            $v /= 1024;
                            $suffix = 'GiB';
                        }
                        if ($v > 1024) {
                            $v /= 1024;
                            $suffix = 'TiB';
                        }

                        return sprintf('%d %s ', $v, $suffix);
                    }
                );
                $response->setBody($imageData);

                return $response;
            }
        );

        $service->get(
            '/stats/users',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $profileId = InputValidation::profileId($request->getQueryParameter('profile_id'));
                $response = new Response(
                    200,
                    'image/png'
                );

                if (false === $stats = $this->getStats()) {
                    throw new HttpException('no stats available', 400);
                }
                $dateUsersList = [];
                foreach ($stats['profiles'][$profileId]['days'] as $v) {
                    $dateUsersList[$v['date']] = $v['unique_user_count'];
                }

                $imageData = $this->graph->draw(
                    $dateUsersList
                );
                $response->setBody($imageData);

                return $response;
            }
        );

        $service->get(
            '/messages',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $messageAction = $request->getPostParameter('message_action');
                switch ($messageAction) {
                    case 'set':
                        // we can only have one "motd", so remove the ones that
                        // already exist
                        $motdMessages = $this->storage->systemMessages('motd');
                        foreach ($motdMessages as $motdMessage) {
                            $this->storage->deleteSystemMessage($motdMessage['id']);
                        }

                        // no need to validate, we accept everything
                        $messageBody = $request->getPostParameter('message_body');
                        $this->storage->addSystemMessage('motd', $messageBody);
                        break;
                    case 'delete':
                        $messageId = InputValidation::messageId($request->getPostParameter('message_id'));
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
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $dateTime = $request->getPostParameter('date_time');
                InputValidation::dateTime($dateTime);
                $ipAddress = $request->getPostParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

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

    /**
     * @param \DateInterval $dateInterval
     *
     * @return array<string, int>
     */
    private function createDateList(DateInterval $dateInterval)
    {
        $currentDay = $this->dateTimeToday->format('Y-m-d');
        $dateTime = clone $this->dateTimeToday;
        $dateTime->sub($dateInterval);
        $oneDay = new DateInterval('P1D');

        $dateList = [];
        while ($dateTime < $this->dateTimeToday) {
            $dateList[$dateTime->format('Y-m-d')] = 0;
            $dateTime->add($oneDay);
        }

        return $dateList;
    }

    /**
     * @return array
     */
    private function getProfileList()
    {
        $profileList = [];
        foreach ($this->config->getSection('vpnProfiles')->toArray() as $profileId => $profileData) {
            $profileConfig = new ProfileConfig($profileData);
            $profileConfigArray = $profileConfig->toArray();
            ksort($profileConfigArray);
            $profileList[$profileId] = $profileConfigArray;
        }

        return $profileList;
    }

    /**
     * @return false|array
     */
    private function getStats()
    {
        $statsFile = sprintf('%s/stats.json', $this->dataDir);
        try {
            return FileIO::readJsonFile($statsFile);
        } catch (RuntimeException $e) {
            // no stats file available yet
            return false;
        }
    }
}
