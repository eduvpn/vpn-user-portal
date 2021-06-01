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
use DateTimeZone;
use LC\Common\Config;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\Exception\HttpException;
use LC\Common\Http\HtmlResponse;
use LC\Common\Http\InputValidation;
use LC\Common\Http\RedirectResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\HttpClient\ServerClient;
use LC\Common\ProfileConfig;
use LC\Common\TplInterface;

class AdminPortalModule implements ServiceModuleInterface
{
    /** @var \LC\Common\TplInterface */
    private $tpl;

    /** @var Storage */
    private $storage;

    /** @var \LC\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(TplInterface $tpl, Storage $storage, ServerClient $serverClient)
    {
        $this->tpl = $tpl;
        $this->storage = $storage;
        $this->serverClient = $serverClient;
        $this->dateTime = new DateTime();
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
                $profileList = $this->serverClient->getRequireArray('profile_list');

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileData) {
                    $idNameMapping[$profileId] = $profileData['displayName'];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'idNameMapping' => $idNameMapping,
                            'vpnConnections' => $this->serverClient->getRequireArray('client_connections'),
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

                $profileConfigList = [];
                $profileList = $this->serverClient->getRequireArray('profile_list');
                foreach ($profileList as $profileId => $profileConfigData) {
                    $profileConfigList[$profileId] = new ProfileConfig(new Config($profileConfigData));
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'profileConfigList' => $profileConfigList,
                            'caInfo' => $this->serverClient->getRequireArray('ca_info'),
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

                $userList = $this->serverClient->getRequireArray('user_list');

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
                $userId = $request->requireQueryParameter('user_id');
                InputValidation::userId($userId);

                $clientCertificateList = $this->serverClient->getRequireArray('client_certificate_list', ['user_id' => $userId]);
                $userMessages = $this->serverClient->getRequireArray('user_messages', ['user_id' => $userId]);

                $userConnectionLogEntries = $this->serverClient->getRequireArray('user_connection_log', ['user_id' => $userId]);

                // get the fancy profile name
                $profileList = $this->serverClient->getRequireArray('profile_list');

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileData) {
                    $idNameMapping[$profileId] = $profileData['displayName'];
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserConfigList',
                        [
                            'userId' => $userId,
                            'userMessages' => $userMessages,
                            'clientCertificateList' => $clientCertificateList,
                            'hasTotpSecret' => $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userId]),
                            'isDisabled' => $this->serverClient->getRequireBool('is_disabled_user', ['user_id' => $userId]),
                            'isSelf' => $adminUserId === $userId, // the admin is viewing their own account
                            'userConnectionLogEntries' => $userConnectionLogEntries,
                            'idNameMapping' => $idNameMapping,
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
                $userId = $request->requirePostParameter('user_id');
                InputValidation::userId($userId);

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
                        $connectionList = $this->serverClient->getRequireArray('client_connections', ['user_id' => $userId]);

                        // disable the user
                        $this->serverClient->post('disable_user', ['user_id' => $userId]);
                        // * revoke all OAuth clients of this user
                        // * delete all client certificates associated with the OAuth clients of this user
                        $clientAuthorizations = $this->storage->getAuthorizations($userId);
                        foreach ($clientAuthorizations as $clientAuthorization) {
                            $this->storage->deleteAuthorization($clientAuthorization['auth_key']);
                            $this->serverClient->post('delete_client_certificates_of_client_id', ['user_id' => $userId, 'client_id' => $clientAuthorization['client_id']]);
                        }

                        // kill all active connections for this user
                        foreach ($connectionList as $profileId => $clientConnectionList) {
                            foreach ($clientConnectionList as $clientInfo) {
                                $this->serverClient->post('kill_client', ['common_name' => $clientInfo['common_name']]);
                            }
                        }
                        break;

                    case 'enableUser':
                        $this->serverClient->post('enable_user', ['user_id' => $userId]);
                        break;

                    case 'deleteCertificates':
                        $this->serverClient->post('delete_client_certificates_of_user_id', ['user_id' => $userId]);
                        break;

                    case 'deleteTotpSecret':
                        $this->serverClient->post('delete_totp_secret', ['user_id' => $userId]);
                        break;

                    default:
                        throw new HttpException('unsupported "user_action"', 400);
                }

                return new RedirectResponse($request->getRootUri().'user?user_id='.$userId);
            }
        );

        $service->get(
            '/log',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $now = new DateTime();

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'now' => $now->format(DateTime::ATOM),
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

                $profileList = $this->serverClient->getRequireArray('profile_list');
                $profileConfigList = [];
                foreach ($profileList as $profileId => $profileConfigData) {
                    $profileConfigList[$profileId] = new ProfileConfig(new Config($profileConfigData));
                }
                $appUsage = self::getAppUsage($this->serverClient->getRequireArray('app_usage'));

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'appUsage' => $appUsage,
                            'statsData' => $this->getStatsData(),
                            'graphStats' => $this->getGraphStats(),
                            'maxConcurrentConnectionLimit' => $this->getMaxConcurrentConnectionLimit($profileList),
                            'profileConfigList' => $profileConfigList,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/messages',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);

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

                $messageAction = $request->requirePostParameter('message_action');
                switch ($messageAction) {
                    case 'set':
                        // we can only have one "motd", so remove the ones that
                        // already exist
                        $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
                        foreach ($motdMessages as $motdMessage) {
                            $this->serverClient->post('delete_system_message', ['message_id' => $motdMessage['id']]);
                        }

                        // no need to validate, we accept everything
                        $messageBody = $request->requirePostParameter('message_body');
                        $this->serverClient->post('add_system_message', ['message_type' => 'motd', 'message_body' => $messageBody]);
                        break;
                    case 'delete':
                        $messageId = InputValidation::messageId($request->requirePostParameter('message_id'));

                        $this->serverClient->post('delete_system_message', ['message_id' => $messageId]);
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

                $dateTime = InputValidation::dateTime($request->requirePostParameter('date_time'));
                $dateTimeLocalStr = $dateTime->format('Y-m-d H:i:s');

                // make sure it is NOT in the future
                $now = new DateTime();
                if ($dateTime > $now) {
                    throw new HttpException('can not specify a time in the future', 400);
                }
                // convert it to UTC as our server logs are all in UTC
                $dateTime->setTimeZone(new DateTimeZone('UTC'));

                $ipAddress = $request->requirePostParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'now' => $now->format(DateTime::ATOM),
                            'date_time' => $dateTimeLocalStr,
                            'ip_address' => $ipAddress,
                            'result' => $this->serverClient->getRequireArrayOrFalse('log', ['date_time' => $dateTime->format('Y-m-d H:i:s'), 'ip_address' => $ipAddress]),
                        ]
                    )
                );
            }
        );
    }

    /**
     * @return array
     */
    private function getStatsData()
    {
        $stats = $this->serverClient->get('stats');
        if (!\is_array($stats) || !\array_key_exists('profiles', $stats)) {
            return [];
        }

        // here we clean up the data obtained from the API, not sure WHAT I was
        // thinking back then...what a shitty format!

        // get a list of all the data for which we want to have the statistics,
        // ideally this is exactly the same the API provides, otherwise the
        // "global" profile statistics may not be right (anymore). Ah well.
        $dateList = [];
        $currentDate = date_sub(clone $this->dateTime, new DateInterval('P1M'));
        while ($currentDate < $this->dateTime) {
            $dateList[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }

        $statsData = [];
        foreach ($stats['profiles'] as $profileId => $profileStats) {
            // the "per profile" (aggregate) stats as determined by the API
            // server, we cannot influence the exact period over which this
            // data was computed, let's hope it was correctly provided!
            $statsData[$profileId] = [
                'unique_user_count' => $profileStats['unique_user_count'],
                'total_traffic' => $profileStats['total_traffic'],
                'max_concurrent_connections_time' => $profileStats['max_concurrent_connections_time'],
                'max_concurrent_connections' => $profileStats['max_concurrent_connections'],
            ];
            // we only want to have the data for the days in dateList
            $dayStats = [];
            foreach ($dateList as $dateStr) {
                $dayStats[$dateStr] = [
                    'bytes_transferred' => 0,
                    'unique_user_count' => 0,
                ];
            }

            foreach ($profileStats['days'] as $dayData) {
                if (\array_key_exists($dayData['date'], $dayStats)) {
                    // we have this day, so replace the data!
                    $dayStats[$dayData['date']] = [
                        'bytes_transferred' => $dayData['bytes_transferred'],
                        'unique_user_count' => $dayData['unique_user_count'],
                    ];
                }
            }

            $statsData[$profileId]['date_list'] = $dayStats;
        }

        return $statsData;
    }

    /**
     * @return array
     */
    private function getGraphStats()
    {
        $outputData = [];
        $statsData = $this->getStatsData();
        foreach ($statsData as $profileId => $profileStats) {
            $outputData[$profileId] = [];
            // find max number of unique users/traffic per day
            $maxUniqueUserCount = 0;
            $maxTrafficCount = 0;
            foreach ($profileStats['date_list'] as $dayData) {
                if ($dayData['unique_user_count'] > $maxUniqueUserCount) {
                    $maxUniqueUserCount = $dayData['unique_user_count'];
                }
                if ($dayData['bytes_transferred'] > $maxTrafficCount) {
                    $maxTrafficCount = $dayData['bytes_transferred'];
                }
            }

            $outputData[$profileId]['max_traffic_count'] = $maxTrafficCount;
            $outputData[$profileId]['max_unique_user_count'] = $maxUniqueUserCount;
            $outputData[$profileId]['date_list'] = [];

            // convert users/traffic to a number between 0 and 25
            $maxUserDivider = $maxUniqueUserCount / 25;
            $maxTrafficDivider = $maxTrafficCount / 25;
            foreach ($profileStats['date_list'] as $dayDate => $dayData) {
                $outputData[$profileId]['date_list'][$dayDate] = [
                    'user_fraction' => 0 === $maxUserDivider ? 0 : (int) floor($dayData['unique_user_count'] / $maxUserDivider),
                    'traffic_fraction' => 0 === $maxTrafficDivider ? 0 : (int) floor($dayData['bytes_transferred'] / $maxTrafficDivider),
                ];
            }
        }

        return $outputData;
    }

    /**
     * @return array
     */
    private function getMaxConcurrentConnectionLimit(array $profileList)
    {
        $maxConcurrentConnectionLimitList = [];
        foreach ($profileList as $profileId => $profileData) {
            list($ipFour, $ipFourPrefix) = explode('/', $profileData['range']);
            $vpnProtoPortsCount = \count($profileData['vpnProtoPorts']);
            $maxConcurrentConnectionLimitList[$profileId] = pow(2, (32 - (int) $ipFourPrefix)) - 4 * $vpnProtoPortsCount;
        }

        return $maxConcurrentConnectionLimitList;
    }

    /**
     * @return array
     */
    private static function getAppUsage(array $appUsage)
    {
        // limit to top 8, we don't care about the small ones...
        $appUsage = \array_slice($appUsage, 0, 8);
        $totalClientCount = 0;
        foreach ($appUsage as $appInfo) {
            $totalClientCount += $appInfo['client_count'];
        }

        $relAppUsage = [];
        $i = 0;
        $cumulativePercent = 0;
        foreach ($appUsage as $appInfo) {
            $appInfo['client_count_rel'] = $appInfo['client_count'] / $totalClientCount;
            $appInfo['client_count_rel_pct'] = (int) round($appInfo['client_count'] / $totalClientCount * 100);
            $appInfo['slice_no'] = $i;
            $appInfo['path_data'] = self::getPathData($cumulativePercent, $appInfo['client_count_rel']);
            $relAppUsage[] = $appInfo;
            ++$i;
        }

        return $relAppUsage;
    }

    /**
     * @param float $cumulativeFraction
     * @param float $sliceFraction
     *
     * @return string
     */
    private static function getPathData(&$cumulativeFraction, $sliceFraction)
    {
        // Lots of ideas from https://medium.com/hackernoon/a-simple-pie-chart-in-svg-dbdd653b6936
        $startXy = self::getCoordinates($cumulativeFraction);
        $cumulativeFraction += $sliceFraction;
        $endXy = self::getCoordinates($cumulativeFraction);
        $largeArcFlag = $sliceFraction > 0.5 ? 1 : 0;

        return sprintf('M %s %s A 1 1 0 %s 1 %s %s L 0 0', $startXy[0], $startXy[1], $largeArcFlag, $endXy[0], $endXy[1]);
    }

    /**
     * @param float $f
     *
     * @return array<float,float>
     */
    private static function getCoordinates($f)
    {
        return [cos(2 * \M_PI * $f), sin(2 * \M_PI * $f)];
    }
}
