<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;

class UserInfo
{
    /** @var string */
    private $userId;

    /** @var array<string> */
    private $permissionList;

    /** @var \DateTime|null */
    private $sessionExpiresAt = null;

    /**
     * @param string        $userId
     * @param array<string> $permissionList
     */
    public function __construct($userId, array $permissionList)
    {
        $this->userId = $userId;
        $this->permissionList = $permissionList;
    }

    /**
     * @param \DateTime $sessionExpiresAt
     *
     * @return void
     */
    public function setSessionExpiresAt(DateTime $sessionExpiresAt)
    {
        $this->sessionExpiresAt = $sessionExpiresAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getSessionExpiresAt()
    {
        return $this->sessionExpiresAt;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return array<string>
     */
    public function getPermissionList()
    {
        return $this->permissionList;
    }
}
