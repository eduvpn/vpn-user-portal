<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Portal;

use DateTime;
use PDO;

class Voucher
{
    /** @var \PDO */
    private $db;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(PDO $db, DateTime $dateTime = null)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->query('PRAGMA foreign_keys = ON');
        }

        $this->db = $db;
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $this->dateTime = $dateTime;
    }

    /**
     * @param string $userId
     * @param string $voucherCode
     *
     * @return void
     */
    public function addVoucher($userId, $voucherCode)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO
                vouchers (user_id, voucher_code, created_at)
            VALUES
                (:user_id, :voucher_code, :created_at)'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':voucher_code', $voucherCode, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $voucherCode
     *
     * @return string|false
     */
    public function getInfo($voucherCode)
    {
        $stmt = $this->db->prepare(
            'SELECT user_id
             FROM
                vouchers
             WHERE voucher_code = :voucher_code
             AND used_at IS NULL'
        );
        $stmt->bindValue(':voucher_code', $voucherCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn(0);
    }

    /**
     * @param string $usedBy
     * @param string $voucherCode
     *
     * @return bool
     */
    public function useVoucher($usedBy, $voucherCode)
    {
        $stmt = $this->db->prepare(
            'UPDATE
                vouchers
             SET
                used_by = :used_by,
                used_at = :used_at
             WHERE
                voucher_code = :voucher_code'
        );
        $stmt->bindValue(':used_by', $usedBy, PDO::PARAM_STR);
        $stmt->bindValue(':voucher_code', $voucherCode, PDO::PARAM_STR);
        $stmt->bindValue(':used_at', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    /**
     * @return void
     */
    public function init()
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS vouchers (
                user_id VARCHAR(255) NOT NULL,
                voucher_code VARCHAR(255) NOT NULL,
                created_at VARCHAR(255) NOT NULL,
                used_by VARCHAR(255) DEFAULT NULL,
                used_at VARCHAR(255) DEFAULT NULL,
                UNIQUE(voucher_code)
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
