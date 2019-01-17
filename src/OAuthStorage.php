<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Portal;

use fkooman\OAuth\Server\Storage;
use fkooman\SqliteMigrate\Migration;
use LetsConnect\Common\HttpClient\ServerClient;
use PDO;

/**
 * By using this class we make sure the user is not disabled by the admin when
 * an "authorization code" or "refresh token" is used.
 *
 * We only need this one because there is currently no way for the admin to
 * delete all "authorizations" for a particular user as part of "disabling"
 * the user. Ideally this will be fixed when we merge the admin/user portals.
 */
class OAuthStorage extends Storage
{
    const CURRENT_SCHEMA_VERSION = '0000000000';

    /** @var \LetsConnect\Common\HttpClient\ServerClient */
    private $serverClient;

    /** @var \fkooman\SqliteMigrate\Migration */
    private $migration;

    /**
     * @param \PDO                                        $db
     * @param string                                      $schemaDir
     * @param \LetsConnect\Common\HttpClient\ServerClient $serverClient
     */
    public function __construct(PDO $db, $schemaDir, ServerClient $serverClient)
    {
        parent::__construct($db);
        $this->serverClient = $serverClient;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
    }

    /**
     * @param string $authKey
     *
     * @return bool
     */
    public function hasAuthorization($authKey)
    {
        $stmt = $this->db->prepare(
            'SELECT
                user_id
             FROM authorizations
             WHERE
                auth_key = :auth_key'
        );

        $stmt->bindValue(':auth_key', $authKey, PDO::PARAM_STR);
        $stmt->execute();

        if (false === $userId = $stmt->fetchColumn(0)) {
            // authorization not found
            return false;
        }

        if ($this->serverClient->get('is_disabled_user', ['user_id' => $userId])) {
            // user is disabled
            return false;
        }

        // authorization exists, and user is not disabled
        return true;
    }

    /**
     * @return void
     */
    public function init()
    {
        $this->migration->init();
    }

    /**
     * @return void
     */
    public function update()
    {
        $this->migration->run();
    }
}
