<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use PDO;

class Stats
{
    /** @var Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param array<string> $profileIdList
     */
    public function get(array $profileIdList): array
    {
        $allStatsData = [];
        $db = $this->storage->getPdo();

        foreach ($profileIdList as $profileId) {
            $statsData = [];
            $timeConnection = [];
            $uniqueUsers = [];

            // we need to fetch one row at a time in order to not exhaust PHP's
            // memory, this is not ideal, but as long as there is no "yield"
            // support in PHP 5.4...
            $stmt = $db->prepare(
<<< 'SQL'
    SELECT 
        user_id,
        common_name, 
        connected_at, 
        disconnected_at, 
        bytes_transferred
    FROM 
        connection_log
    WHERE
        profile_id = :profile_id
    AND
        disconnected_at IS NOT NULL
    ORDER BY
        connected_at
SQL
            );

            $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
            $stmt->execute();
            while ($entry = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userId = $entry['user_id'];
                $connectedAt = $entry['connected_at'];
                $disconnectedAt = $entry['disconnected_at'];
                $connectedAtDateTime = new DateTime($entry['connected_at']);
                $dateOfConnection = $connectedAtDateTime->format('Y-m-d');

                if (!\array_key_exists($dateOfConnection, $statsData)) {
                    $statsData[$dateOfConnection] = [
                        'number_of_connections' => 0,
                        'bytes_transferred' => 0,
                        'unique_user_list' => [],
                    ];
                }

                ++$statsData[$dateOfConnection]['number_of_connections'];
                $statsData[$dateOfConnection]['bytes_transferred'] += $entry['bytes_transferred'];

                // add it to table to be able to determine max concurrent connection
                // count
                if (!\array_key_exists($connectedAt, $timeConnection)) {
                    $timeConnection[$connectedAt] = [];
                }
                $timeConnection[$connectedAt][] = 'C';

                if (null !== $disconnectedAt) {
                    if (!\array_key_exists($disconnectedAt, $timeConnection)) {
                        $timeConnection[$disconnectedAt] = [];
                    }
                    $timeConnection[$disconnectedAt][] = 'D';
                }

                // unique user list per day
                if (!\in_array($userId, $statsData[$dateOfConnection]['unique_user_list'], true)) {
                    $statsData[$dateOfConnection]['unique_user_list'][] = $userId;
                }

                // unique user list for the whole logging period
                if (!\in_array($userId, $uniqueUsers, true)) {
                    $uniqueUsers[] = $userId;
                }
            }

            ksort($timeConnection);
            $maxConcurrentConnections = 0;
            $maxConcurrentConnectionsTime = 0;
            $concurrentConnections = 0;
            foreach ($timeConnection as $unixTime => $eventArray) {
                foreach ($eventArray as $event) {
                    if ('C' === $event) {
                        ++$concurrentConnections;
                        if ($concurrentConnections > $maxConcurrentConnections) {
                            $maxConcurrentConnections = $concurrentConnections;
                            $maxConcurrentConnectionsTime = $unixTime;
                        }
                    } else {
                        --$concurrentConnections;
                    }
                }
            }

            $totalTraffic = 0;
            // convert the user list in unique user count for that day, rework array
            // key and determine total amount of traffic
            foreach ($statsData as $date => $entry) {
                $statsData[$date]['date'] = $date;
                $statsData[$date]['unique_user_count'] = \count($entry['unique_user_list']);
                unset($statsData[$date]['unique_user_list']);
                $totalTraffic += $entry['bytes_transferred'];
            }

            $allStatsData[$profileId] = [
                'days' => array_values($statsData),
                'total_traffic' => $totalTraffic,
                'max_concurrent_connections' => $maxConcurrentConnections,
                'max_concurrent_connections_time' => $maxConcurrentConnectionsTime,
                'unique_user_count' => \count($uniqueUsers),
            ];
        }

        return $allStatsData;
    }
}
