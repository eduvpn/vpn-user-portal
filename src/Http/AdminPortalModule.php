<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Http;

use DateTimeImmutable;
use DateTimeZone;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConfigCheck;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\Environment;
use Vpn\Portal\Http\Exception\HttpException;
use Vpn\Portal\ServerInfo;
use Vpn\Portal\Storage;
use Vpn\Portal\TplInterface;
use Vpn\Portal\Validator;

class AdminPortalModule implements ServiceModuleInterface
{
    private Config $config;
    private TplInterface $tpl;
    private ConnectionManager $connectionManager;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private ServerInfo $serverInfo;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, TplInterface $tpl, ConnectionManager $connectionManager, Storage $storage, OAuthStorage $oauthStorage, ServerInfo $serverInfo)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->connectionManager = $connectionManager;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->serverInfo = $serverInfo;
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/connections',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'profileConfigList' => $this->config->profileConfigList(),
                            'profileConnectionList' => $this->connectionManager->get(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'nodeInfoList' => $this->connectionManager->nodeInfo(),
                            'profileConfigList' => $this->config->profileConfigList(),
                            'serverInfo' => $this->serverInfo,
                            'serverProblemList' => Environment::verify(),
                            'profileProblemList' => ConfigCheck::verify($this->config),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $userList = $this->storage->userList();

                $listUsers = $request->optionalQueryParameter('list_users', fn (string $s) => Validator::listUsers($s));
                if ('disabled' === $listUsers) {
                    $userList = array_filter($userList, fn (UserInfo $v) => $v->isDisabled());
                } elseif ('active' === $listUsers) {
                    $userList = array_filter($userList, fn (UserInfo $v) => !$v->isDisabled());
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserList',
                        [
                            'listUsers' => $listUsers,
                            'userList' => $userList,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/user',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                $adminUserId = $userInfo->userId();
                $userId = $request->requireQueryParameter('user_id', fn (string $s) => Validator::userId($s));
                if (null === $managedUserInfo = $this->storage->userInfo($userId)) {
                    throw new HttpException('account does not exist', 404);
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserConfigList',
                        [
                            'userId' => $userId,
                            'profileConfigList' => $this->config->profileConfigList(),
                            'configList' => VpnPortalModule::filterConfigList($this->storage, $userId),
                            'isDisabled' => $managedUserInfo->isDisabled(),
                            'authData' => $managedUserInfo->authData(),
                            'isSelf' => $adminUserId === $userId, // the admin is viewing their own account
                            'userConnectionLogEntries' => $this->storage->getConnectionLogForUser($userId),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/user_disable_account',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $userId = self::validateUser($request, $userInfo);
                $this->storage->userDisable($userId);

                // delete and disconnect all (active) VPN configurations
                // for this user
                $this->connectionManager->disconnectByUserId($userId);

                // revoke all OAuth authorizations
                foreach ($this->oauthStorage->getAuthorizations($userId) as $clientAuthorization) {
                    $this->oauthStorage->deleteAuthorization($clientAuthorization->authKey());
                }

                return new RedirectResponse($request->getRootUri().'user?user_id='.$userId);
            }
        );

        $service->post(
            '/user_enable_account',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $userId = self::validateUser($request, $userInfo);

                // enabling the account will again allow the authorization of
                // OAuth clients, allow OpenVPN connections again and sync the
                // WireGuard peer configurations to the daemon(s)
                $this->storage->userEnable($userId);

                return new RedirectResponse($request->getRootUri().'user?user_id='.$userId);
            }
        );

        $service->post(
            '/user_delete_account',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $userId = self::validateUser($request, $userInfo);

                // delete and disconnect all (active) VPN configurations
                // for this user
                $this->connectionManager->disconnectByUserId($userId);

                // delete all user data (except log)
                $this->storage->userDelete($userId);

                if ('DbAuthModule' === $this->config->authModule()) {
                    // remove the user from the local database
                    $this->storage->localUserDelete($userId);
                }

                return new RedirectResponse($request->getRootUri().'users');
            }
        );

        $service->get(
            '/log',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'now' => $this->dateTime,
                            'date_time' => null,
                            'ip_address' => null,
                            'logEntries' => [],
                            'showResults' => false,
                        ]
                    )
                );
            }
        );

        $service->get(
            '/stats',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                // XXX this is not really testable, should base it on
                // $this->dateTime somehow...
                $oneWeekAgo = Dt::get('today -1 week', new DateTimeZone('UTC'));

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'profileConfigList' => $this->config->profileConfigList(),
                            'statsMaxConnectionCount' => $this->storage->statsGetLiveMaxConnectionCount(),
                            'statsUniqueUserCount' => $this->storage->statsGetUniqueUsers($oneWeekAgo),
                            'statsUniqueGuestUserCount' => $this->config->apiConfig()->enableGuestAccess() ? $this->storage->statsGetUniqueGuestUsers($oneWeekAgo) : null,
                            'appUsage' => self::appUsage($this->storage->appUsage()),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/csv_stats/live',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $profileId = $request->requireQueryParameter('profile_id', fn (string $s) => Validator::profileId($s));

                $csvString = 'Date/Time,#Connections'.PHP_EOL;
                foreach ($this->storage->statsGetLive($profileId) as $statsEntry) {
                    $csvString .= sprintf(
                        '%s,%d',
                        $statsEntry['date_time']->format('Y-m-d\TH:i:s'),
                        $statsEntry['connection_count']
                    ).PHP_EOL;
                }

                return new Response(
                    $csvString,
                    [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => sprintf('attachment; filename="%s_%s_live_stats.csv"', $request->getServerName(), $profileId),
                    ]
                );
            }
        );

        $service->get(
            '/csv_stats/aggregate',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);
                $profileId = $request->requireQueryParameter('profile_id', fn (string $s) => Validator::profileId($s));

                return new Response(
                    $this->aggregateStatsCsvString($profileId),
                    [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => sprintf('attachment; filename="%s_%s_aggregate_stats.csv"', $request->getServerName(), $profileId),
                    ]
                );
            }
        );

        $service->post(
            '/log',
            function (Request $request, UserInfo $userInfo): Response {
                $this->requireAdmin($userInfo);

                $dateTime = new DateTimeImmutable(
                    $request->requirePostParameter('date_time', fn (string $s) => Validator::dateTime($s)),
                    new DateTimeZone('UTC')
                );

                // make sure it is NOT in the future
                if ($dateTime > $this->dateTime) {
                    throw new HttpException('can not specify a time in the future', 400);
                }

                $ipAddress = $request->requirePostParameter('ip_address', fn (string $s) => Validator::ipAddress($s));

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'profileConfigList' => $this->config->profileConfigList(),
                            'now' => $this->dateTime,
                            'date_time' => $dateTime,
                            'ip_address' => $ipAddress,
                            'logEntries' => $this->storage->getLogEntries($dateTime, $ipAddress),
                            'showResults' => true,
                        ]
                    )
                );
            }
        );
    }

    private function requireAdmin(UserInfo $userInfo): void
    {
        if (!$userInfo->isAdmin()) {
            throw new HttpException('user is not an administrator', 403);
        }
    }

    /**
     * Validate the user ID, make sure the admin is not performing actions on
     * their own account, and make sure the user actually exists.
     */
    private function validateUser(Request $request, UserInfo $userInfo): string
    {
        $userId = $request->requirePostParameter('user_id', fn (string $s) => Validator::userId($s));
        if (null === $this->storage->userInfo($userId)) {
            throw new HttpException('account does not exist', 404);
        }

        if ($userInfo->userId() === $userId) {
            throw new HttpException('cannot manage own account', 400);
        }

        return $userId;
    }

    private function aggregateStatsCsvString(string $profileId): string
    {
        if ($this->config->apiConfig()->enableGuestAccess()) {
            $csvString = 'Date,#Unique Users,#Unique Guest Users,Max #Connections'.PHP_EOL;
            foreach ($this->storage->statsGetAggregate($profileId) as $statsEntry) {
                $csvString .= sprintf(
                    '%s,%d,%d,%d',
                    $statsEntry['date'],
                    $statsEntry['unique_user_count'],
                    $statsEntry['unique_guest_user_count'],
                    $statsEntry['max_connection_count']
                ).PHP_EOL;
            }

            return $csvString;
        }

        $csvString = 'Date,#Unique Users,Max #Connections'.PHP_EOL;
        foreach ($this->storage->statsGetAggregate($profileId) as $statsEntry) {
            $csvString .= sprintf(
                '%s,%d,%d',
                $statsEntry['date'],
                $statsEntry['unique_user_count'],
                $statsEntry['max_connection_count']
            ).PHP_EOL;
        }

        return $csvString;
    }

    /**
     * @return array<array{client_id:string,client_count:int,client_count_rel:float,client_count_rel_pct:int,slice_no:int,path_data:string}>
     */
    private static function appUsage(array $appUsage): array
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
        return [cos(2 * M_PI * $f), sin(2 * M_PI * $f)];
    }
}
