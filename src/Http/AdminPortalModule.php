<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use LC\Portal\CA\CaInterface;
use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\Json;
use LC\Portal\LoggerInterface;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\TplInterface;
use RuntimeException;

class AdminPortalModule implements ServiceModuleInterface
{
    private string $dataDir;
    private Config $config;
    private TplInterface $tpl;
    private CaInterface $ca;
    private DaemonWrapper $daemonWrapper;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private AdminHook $adminHook;
    private DateTimeImmutable $dateTime;

    public function __construct(string $dataDir, Config $config, TplInterface $tpl, CaInterface $ca, DaemonWrapper $daemonWrapper, Storage $storage, OAuthStorage $oauthStorage, AdminHook $adminHook)
    {
        $this->dataDir = $dataDir;
        $this->config = $config;
        $this->tpl = $tpl;
        $this->ca = $ca;
        $this->daemonWrapper = $daemonWrapper;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->adminHook = $adminHook;
        $this->dateTime = new DateTimeImmutable();
    }

    public function init(Service $service): void
    {
        $service->get(
            '/connections',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                // get the fancy profile name
                $profileList = $this->profileList();

                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileConfig) {
                    $idNameMapping[$profileId] = $profileConfig->displayName();
                }

                $connectionList = $this->daemonWrapper->getConnectionList(null, null);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'idNameMapping' => $idNameMapping,
                            'vpnConnections' => $connectionList,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $profileList = $this->profileList();

                $certData = $this->ca->caCert();
                // XXX probably wrap all this stuff in its own class...
                $parsedCert = openssl_x509_parse($certData);
                $validFrom = new DateTimeImmutable('@'.$parsedCert['validFrom_time_t']);
                $validTo = new DateTimeImmutable('@'.$parsedCert['validTo_time_t']);

                $caInfo = [
                    'valid_from' => $validFrom->format(DateTimeImmutable::ATOM),
                    'valid_to' => $validTo->format(DateTimeImmutable::ATOM),
                ];

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            // XXX rename template var to profileList
                            'profileConfigList' => $profileList,
                            'caInfo' => $caInfo,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

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
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $adminUserId = $userInfo->getUserId();
                $userId = $request->requireQueryParameter('user_id');
                InputValidation::userId($userId);

                $clientCertificateList = $this->storage->getCertificates($userId);
                $userMessages = $this->storage->getUserLog($userId);
                $userConnectionLogEntries = $this->storage->getConnectionLogForUser($userId);
                // get the fancy profile name
                $profileList = $this->profileList();
                $idNameMapping = [];
                foreach ($profileList as $profileId => $profileConfig) {
                    $idNameMapping[$profileId] = $profileConfig->displayName();
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserConfigList',
                        [
                            'userId' => $userId,
                            'userMessages' => $userMessages,
                            'clientCertificateList' => $clientCertificateList,
                            'isDisabled' => $this->storage->userIsDisabled($userId),
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
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

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
                        $connectionList = $this->daemonWrapper->getConnectionList($userId, null);

                        // disable the user
                        $this->storage->disableUser($userId);
                        $this->storage->addUserLog($userId, LoggerInterface::NOTICE, 'account disabled by admin', $this->dateTime);

                        // * revoke all OAuth clients of this user
                        // * delete all client certificates associated with the OAuth clients of this user
                        $clientAuthorizations = $this->oauthStorage->getAuthorizations($userId);
                        foreach ($clientAuthorizations as $clientAuthorization) {
                            $this->oauthStorage->deleteAuthorization($clientAuthorization->authKey());
                            $this->storage->addUserLog(
                                $userId,
                                LoggerInterface::NOTICE,
                                sprintf('certificate(s) for OAuth client "%s" deleted', $clientAuthorization->clientId()),
                                $this->dateTime
                            );

                            $this->storage->deleteCertificatesOfClientId($userId, $clientAuthorization->clientId());
                        }

                        // kill all active connections for this user
                        foreach ($connectionList as $profileId => $clientConnectionList) {
                            foreach ($clientConnectionList as $clientInfo) {
                                $this->daemonWrapper->killClient($clientInfo['common_name']);
                            }
                        }
                        break;

                    case 'enableUser':
                        $this->storage->userEnable($userId);
                        $this->storage->addUserLog($userId, LoggerInterface::NOTICE, 'account enabled by admin', $this->dateTime);

                        break;

                    case 'deleteUser':
                        $this->storage->userDelete($userId);
                        if ('DbAuthModule' === $this->config->requireString('authModule', 'DbAuthModule')) {
                            $this->storage->localUserDelete($userId);
                        }
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
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $now = new DateTimeImmutable();

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'now' => $now->format(DateTimeImmutable::ATOM),
                            'date_time' => null,
                            'ip_address' => null,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/stats',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $profileList = $this->profileList();
                $appUsage = self::getAppUsage($this->storage->getAppUsage());

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'appUsage' => $appUsage,
                            'statsData' => $this->getStatsData(),
                            'graphStats' => $this->getGraphStats(),
                            'maxConcurrentConnectionLimit' => $this->getMaxConcurrentConnectionLimit($profileList),
                            // XXX rename to profileList
                            'profileConfigList' => $profileList,
                        ]
                    )
                );
            }
        );

        $service->post(
            '/log',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $dateTime = InputValidation::dateTime($request->requirePostParameter('date_time'));
                $dateTimeLocalStr = $dateTime->format('Y-m-d H:i:s');

                // make sure it is NOT in the future
                $now = new DateTimeImmutable();
                if ($dateTime > $now) {
                    throw new HttpException('can not specify a time in the future', 400);
                }
                // convert it to UTC as our server logs are all in UTC
                // XXX does this work when immutable?
                $dateTime->setTimeZone(new DateTimeZone('UTC'));

                $ipAddress = $request->requirePostParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'now' => $now->format(DateTimeImmutable::ATOM),
                            'date_time' => $dateTimeLocalStr,
                            'ip_address' => $ipAddress,
                            'result' => $this->storage->getLogEntry($dateTime, $ipAddress),
                        ]
                    )
                );
            }
        );
    }

    private function requireAdmin(UserInfo $userInfo): void
    {
        if (!$this->adminHook->isAdmin($userInfo)) {
            throw new HttpException('user is not an administrator', 403);
        }
    }

    private function getStatsData(): array
    {
        // XXX probably do not use dataDir here directly, but a nice class
        $statsFile = sprintf('%s/stats.json', $this->dataDir);
        try {
            $stats = Json::decode(FileIO::readFile($statsFile));
        } catch (RuntimeException $e) {
            // unable to read stats file
            return [];
        }

        if (!\array_key_exists('profiles', $stats)) {
            // broken stats file
            return [];
        }

        // here we clean up the data obtained from the API, not sure WHAT I was
        // thinking back then...what a shitty format!

        // get a list of all the data for which we want to have the statistics,
        // ideally this is exactly the same the API provides, otherwise the
        // "global" profile statistics may not be right (anymore). Ah well.
        $dateList = [];
        $currentDate = $this->dateTime->sub(new DateInterval('P1M'));
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

    private function getGraphStats(): array
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

    private function getMaxConcurrentConnectionLimit(array $profileList): array
    {
        $maxConcurrentConnectionLimitList = [];
        foreach ($profileList as $profileId => $profileData) {
            [$ipFour, $ipFourPrefix] = explode('/', $profileData->range());
            $vpnProtoPortsCount = \count($profileData->vpnProtoPorts());
            $maxConcurrentConnectionLimitList[$profileId] = 2 ** (32 - (int) $ipFourPrefix) - 4 * $vpnProtoPortsCount;
        }

        return $maxConcurrentConnectionLimitList;
    }

    private static function getAppUsage(array $appUsage): array
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

    private static function getPathData(float &$cumulativeFraction, float $sliceFraction): string
    {
        // Lots of ideas from https://medium.com/hackernoon/a-simple-pie-chart-in-svg-dbdd653b6936
        $startXy = self::getCoordinates($cumulativeFraction);
        $cumulativeFraction += $sliceFraction;
        $endXy = self::getCoordinates($cumulativeFraction);
        $largeArcFlag = $sliceFraction > 0.5 ? 1 : 0;

        return sprintf('M %s %s A 1 1 0 %s 1 %s %s L 0 0', $startXy[0], $startXy[1], $largeArcFlag, $endXy[0], $endXy[1]);
    }

    /**
     * @return array{float,float}
     */
    private static function getCoordinates(float $f): array
    {
        return [cos(2 * \M_PI * $f), sin(2 * \M_PI * $f)];
    }

    /**
     * XXX duplicate in VpnPortalModule|VpnApiModule.
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
