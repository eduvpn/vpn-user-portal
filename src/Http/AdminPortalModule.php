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
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use LC\Portal\Config;
use LC\Portal\ConfigCheck;
use LC\Portal\ConnectionManager;
use LC\Portal\Dt;
use LC\Portal\Http\Exception\HttpException;
use LC\Portal\LoggerInterface;
use LC\Portal\ServerInfo;
use LC\Portal\Storage;
use LC\Portal\TplInterface;
use LC\Portal\Validator;
use LC\Portal\VpnDaemon;

class AdminPortalModule implements ServiceModuleInterface
{
    private Config $config;
    private TplInterface $tpl;
    private VpnDaemon $vpnDaemon;
    private ConnectionManager $connectionManager;
    private Storage $storage;
    private OAuthStorage $oauthStorage;
    private AdminHook $adminHook;
    private ServerInfo $serverInfo;
    private DateTimeImmutable $dateTime;

    public function __construct(Config $config, TplInterface $tpl, VpnDaemon $vpnDaemon, ConnectionManager $connectionManager, Storage $storage, OAuthStorage $oauthStorage, AdminHook $adminHook, ServerInfo $serverInfo)
    {
        $this->config = $config;
        $this->tpl = $tpl;
        $this->vpnDaemon = $vpnDaemon;
        $this->connectionManager = $connectionManager;
        $this->storage = $storage;
        $this->oauthStorage = $oauthStorage;
        $this->adminHook = $adminHook;
        $this->serverInfo = $serverInfo;
        $this->dateTime = Dt::get();
    }

    public function init(ServiceInterface $service): void
    {
        $service->get(
            '/connections',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                // get the fancy profile name
                $profileConfigList = $this->config->profileConfigList();

                $idNameMapping = [];
                foreach ($profileConfigList as $profileConfig) {
                    $idNameMapping[$profileConfig->profileId()] = $profileConfig->displayName();
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminConnections',
                        [
                            'idNameMapping' => $idNameMapping,
                            'profileConnectionList' => $this->connectionManager->get(),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                // query all nodes to have them report their status info
                $nodeInfoList = [];
                foreach ($this->config->profileConfigList() as $profileConfig) {
                    for ($i = 0; $i < $profileConfig->nodeCount(); ++$i) {
                        $nodeUrl = $profileConfig->nodeUrl($i);
                        if (!\array_key_exists($nodeUrl, $nodeInfoList)) {
                            $nodeInfoList[$nodeUrl] = $this->vpnDaemon->nodeInfo($nodeUrl);
                        }
                    }
                }

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'nodeInfoList' => $nodeInfoList,
                            'profileConfigList' => $this->config->profileConfigList(),
                            'serverInfo' => $this->serverInfo,
                            'problemList' => ConfigCheck::verify($this->config),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $userList = $this->storage->userList();

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

                $adminUserId = $userInfo->userId();
                $userId = $request->requireQueryParameter('user_id', fn (string $s) => Validator::userId($s));
                if (!$this->storage->userExists($userId)) {
                    throw new HttpException('account does not exist', 404);
                }

                $clientCertificateList = $this->storage->oCertListByUserId($userId);
                // XXX add WG as well
                $userMessages = $this->storage->userLog($userId);
                $userConnectionLogEntries = $this->storage->getConnectionLogForUser($userId);
                // get the fancy profile name
                $profileConfigList = $this->config->profileConfigList();
                $idNameMapping = [];
                foreach ($profileConfigList as $profileConfig) {
                    $idNameMapping[$profileConfig->profileId()] = $profileConfig->displayName();
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
            '/user_disable_account',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);
                $userId = self::validateUser($request, $userInfo);

                $this->storage->userDisable($userId);
                $clientAuthorizations = $this->oauthStorage->getAuthorizations($userId);
                foreach ($clientAuthorizations as $clientAuthorization) {
                    // delete and disconnect all (active) configurations
                    // for this OAuth client authorization
                    $this->connectionManager->disconnectByAuthKey($clientAuthorization->authKey());
                    $this->oauthStorage->deleteAuthorization($clientAuthorization->authKey());
                }

                // XXX mark all non-OAuth configurations as disabled and
                // disconnect them if necessary
                return new RedirectResponse($request->getRootUri().'user?user_id='.$userId);
            }
        );

        $service->post(
            '/user_enable_account',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);
                $userId = self::validateUser($request, $userInfo);

                $this->storage->userEnable($userId);
                $this->storage->userLogAdd($userId, LoggerInterface::NOTICE, 'account enabled by admin', $this->dateTime);

                // XXX unmark all non-OAuth configuratoins as enabled again
                return new RedirectResponse($request->getRootUri().'user?user_id='.$userId);
            }
        );

        $service->post(
            '/user_delete_account',
            function (UserInfo $userInfo, Request $request): Response {
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
            function (UserInfo $userInfo, Request $request): Response {
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
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $profileConfigList = $this->config->profileConfigList();

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminStats',
                        [
                            'appUsage' => self::appUsage($this->storage->appUsage()),
                        ]
                    )
                );
            }
        );

        $service->post(
            '/log',
            function (UserInfo $userInfo, Request $request): Response {
                $this->requireAdmin($userInfo);

                $dateTime = new DateTimeImmutable(
                    $request->requirePostParameter('date_time', fn (string $s) => Validator::dateTime($s))
                );
                // XXX make sure it works correctly regarding timezone!

                // make sure it is NOT in the future
                if ($dateTime > $this->dateTime) {
                    throw new HttpException('can not specify a time in the future', 400);
                }

                $ipAddress = $request->requirePostParameter('ip_address', fn (string $s) => Validator::ipAddress($s));

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
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
        if (!$this->adminHook->isAdmin($userInfo)) {
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
        if (!$this->storage->userExists($userId)) {
            throw new HttpException('account does not exist', 404);
        }

        if ($userInfo->userId() === $userId) {
            throw new HttpException('cannot manage own account', 400);
        }

        return $userId;
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
