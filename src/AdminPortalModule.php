<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use DateInterval;
use DateTime;
use LetsConnect\Common\Http\AuthUtils;
use LetsConnect\Common\Http\Exception\HttpException;
use LetsConnect\Common\Http\HtmlResponse;
use LetsConnect\Common\Http\InputValidation;
use LetsConnect\Common\Http\RedirectResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\TplInterface;

class AdminPortalModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\TplInterface */
    private $tpl;

    /** @var Storage */
    private $storage;

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var Graph */
    private $graph;

    /** @var \DateTime */
    private $dateTimeToday;

    /**
     * @param \LetsConnect\Common\TplInterface            $tpl
     * @param Storage                                     $storage
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     * @param Graph                                       $graph
     */
    public function __construct(TplInterface $tpl, Storage $storage, ServerClient $serverClient, Graph $graph)
    {
        $this->tpl = $tpl;
        $this->storage = $storage;
        $this->serverClient = $serverClient;
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
             * @return \LetsConnect\Common\Http\Response
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
                            'connections' => $this->serverClient->getRequireArray('client_connections'),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/info',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminInfo',
                        [
                            'profileList' => $this->serverClient->getRequireArray('profile_list'),
                        ]
                    )
                );
            }
        );

        $service->get(
            '/users',
            /**
             * @return \LetsConnect\Common\Http\Response
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);
                $adminUserId = $hookData['auth']->id();
                $userId = $request->getQueryParameter('user_id');
                InputValidation::userId($userId);

                $clientCertificateList = $this->serverClient->getRequireArray('client_certificate_list', ['user_id' => $userId]);
                $userMessages = $this->serverClient->getRequireArray('user_messages', ['user_id' => $userId]);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminUserConfigList',
                        [
                            'userId' => $userId,
                            'userMessages' => $userMessages,
                            'clientCertificateList' => $clientCertificateList,
                            'hasTotpSecret' => $this->serverClient->getRequireBool('has_totp_secret', ['user_id' => $userId]),
                            'isDisabled' => $this->serverClient->getRequireBool('is_disabled_user', ['user_id' => $userId]),
                            'isSelf' => $adminUserId === $userId, // the admin is viewing his own account
                        ]
                    )
                );
            }
        );

        $service->post(
            '/user',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);
                $adminUserId = $hookData['auth']->id();
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
                        $this->serverClient->post('disable_user', ['user_id' => $userId]);
                        // * revoke all OAuth clients of this user
                        // * delete all client certificates associated with the OAuth clients of this user
                        $clientAuthorizations = $this->storage->getAuthorizations($userId);
                        foreach ($clientAuthorizations as $clientAuthorization) {
                            $this->storage->deleteAuthorization($userId, $clientAuthorization['client_id'], $clientAuthorization['scope']);
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
                        $this->serverClient->post('enable_user', ['user_id' => $userId]);
                        break;

                    case 'deleteTotpSecret':
                        $this->serverClient->post('delete_totp_secret', ['user_id' => $userId]);
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
             * @return \LetsConnect\Common\Http\Response
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $stats = $this->serverClient->get('stats');
                if (!\is_array($stats) || !\array_key_exists('profiles', $stats)) {
                    // this is an old "stats" format we no longer support,
                    // vpn-server-api-stats has to run again first, which is
                    // done by the crontab running at midnight...
                    $stats = false;
                }
                // get the fancy profile name
                $profileList = $this->serverClient->getRequireArray('profile_list');

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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $profileId = InputValidation::profileId($request->getQueryParameter('profile_id'));
                $response = new Response(
                    200,
                    'image/png'
                );

                $stats = $this->serverClient->getRequireArray('stats');
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $profileId = InputValidation::profileId($request->getQueryParameter('profile_id'));
                $response = new Response(
                    200,
                    'image/png'
                );

                $stats = $this->serverClient->getRequireArray('stats');
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
             * @return \LetsConnect\Common\Http\Response
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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $messageAction = $request->getPostParameter('message_action');
                switch ($messageAction) {
                    case 'set':
                        // we can only have one "motd", so remove the ones that
                        // already exist
                        $motdMessages = $this->serverClient->getRequireArray('system_messages', ['message_type' => 'motd']);
                        foreach ($motdMessages as $motdMessage) {
                            $this->serverClient->post('delete_system_message', ['message_id' => $motdMessage['id']]);
                        }

                        // no need to validate, we accept everything
                        $messageBody = $request->getPostParameter('message_body');
                        $this->serverClient->post('add_system_message', ['message_type' => 'motd', 'message_body' => $messageBody]);
                        break;
                    case 'delete':
                        $messageId = InputValidation::messageId($request->getPostParameter('message_id'));

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
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireAdmin($hookData);

                $dateTime = $request->getPostParameter('date_time');
                InputValidation::dateTime($dateTime);
                $ipAddress = $request->getPostParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

                return new HtmlResponse(
                    $this->tpl->render(
                        'vpnAdminLog',
                        [
                            'currentDate' => date('Y-m-d H:i:s'),
                            'date_time' => $dateTime,
                            'ip_address' => $ipAddress,
                            'result' => $this->serverClient->getRequireArrayOrFalse('log', ['date_time' => $dateTime, 'ip_address' => $ipAddress]),
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
}
