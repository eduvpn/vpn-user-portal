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
use LC\Portal\Http\Exception\InputValidationException;
use LC\Portal\Json;

/**
 * XXX this can probably be done much better by moving it to relevant places.
 */
class InputValidation
{
    /**
     * @param string $displayName
     *
     * @return string
     */
    public static function displayName($displayName)
    {
        self::requireUtf8($displayName, 'displayName');

        $displayName = filter_var($displayName, \FILTER_UNSAFE_RAW, \FILTER_FLAG_STRIP_LOW);

        if (0 === mb_strlen($displayName)) {
            throw new InputValidationException('invalid "display_name"');
        }

        return $displayName;
    }

    /**
     * @param string $commonName
     *
     * @return string
     */
    public static function commonName($commonName)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/', $commonName)) {
            throw new InputValidationException('invalid "common_name"');
        }

        return $commonName;
    }

    /**
     * @param string $serverCommonName
     *
     * @return string
     */
    public static function serverCommonName($serverCommonName)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $serverCommonName)) {
            throw new InputValidationException('invalid "server_common_name"');
        }

        return $serverCommonName;
    }

    /**
     * @param string $profileId
     *
     * @return string
     */
    public static function profileId($profileId)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $profileId)) {
            throw new InputValidationException('invalid "profile_id"');
        }

        return $profileId;
    }

    /**
     * @param string $clientId
     *
     * @return string
     */
    public static function clientId($clientId)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new InputValidationException('invalid "client_id"');
        }

        return $clientId;
    }

    /**
     * @param string $dateTime
     *
     * @return \DateTimeImmutable
     */
    public static function dateTime($dateTime)
    {
        if (false === $dateTimeObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime)) {
            throw new InputValidationException('invalid "date_time"');
        }

        return $dateTimeObj;
    }

    /**
     * @param string $userId
     *
     * @return string
     */
    public static function userId($userId)
    {
        self::requireUtf8($userId, 'userId');

        $userIdLength = mb_strlen($userId);
        if (0 >= $userIdLength || 256 < $userIdLength) {
            throw new InputValidationException('invalid length: 0 < userId <= 256');
        }

        return $userId;
    }

    /**
     * @param string $ipAddress
     *
     * @return string
     */
    public static function ipAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            throw new InputValidationException('invalid "ip_address"');
        }

        // normalize the IP address (only makes a difference for IPv6)
        return inet_ntop(inet_pton($ipAddress));
    }

    public static function ipFour(string $ipFour): string
    {
        if (false === filter_var($ipFour, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            throw new InputValidationException('invalid "ipFour"');
        }

        return $ipFour;
    }

    public static function ipSix(string $ipSix): string
    {
        if (false === filter_var($ipSix, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            throw new InputValidationException('invalid "ipSix"');
        }

        // normalize the IPv6 address
        return inet_ntop(inet_pton($ipSix));
    }

    /**
     * @param string $connectedAt
     *
     * @return int
     */
    public static function connectedAt($connectedAt)
    {
        if (!is_numeric($connectedAt) || 0 > (int) $connectedAt) {
            throw new InputValidationException('invalid "connected_at"');
        }

        return (int) $connectedAt;
    }

    /**
     * @param string $disconnectedAt
     *
     * @return int
     */
    public static function disconnectedAt($disconnectedAt)
    {
        if (!is_numeric($disconnectedAt) || 0 > (int) $disconnectedAt) {
            throw new InputValidationException('invalid "disconnected_at"');
        }

        return (int) $disconnectedAt;
    }

    /**
     * @param string $bytesTransferred
     *
     * @return int
     */
    public static function bytesTransferred($bytesTransferred)
    {
        if (!is_numeric($bytesTransferred) || 0 > (int) $bytesTransferred) {
            throw new InputValidationException('invalid "bytes_transferred"');
        }

        return (int) $bytesTransferred;
    }

    /**
     * @param string $messageId
     *
     * @return int
     */
    public static function messageId($messageId)
    {
        if (!is_numeric($messageId) || 0 >= $messageId) {
            throw new InputValidationException('invalid "message_id"');
        }

        return (int) $messageId;
    }

    /**
     * @param string $messageType
     *
     * @return string
     */
    public static function messageType($messageType)
    {
        if ('motd' !== $messageType && 'notification' !== $messageType && 'maintenance' !== $messageType) {
            throw new InputValidationException('invalid "message_type"');
        }

        return $messageType;
    }

    /**
     * @param string $voucherCode
     *
     * @return string
     */
    public static function voucherCode($voucherCode)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/', $voucherCode)) {
            throw new InputValidationException('invalid "voucherCode"');
        }

        return $voucherCode;
    }

    /**
     * @param string $expiresAt
     *
     * @return \DateTimeImmutable
     */
    public static function expiresAt($expiresAt)
    {
        if (false === $dateTime = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $expiresAt)) {
            throw new InputValidationException('invalid "expires_at"');
        }

        return $dateTime;
    }

    /**
     * Make sure the input string is valid JSON array containing just strings.
     *
     * @param string $permissionListStr
     *
     * @return array<string>
     */
    public static function permissionList($permissionListStr)
    {
        // make sure the string is valid JSON array containing just strings
        $permissionList = Json::decode($permissionListStr);
        foreach ($permissionList as $permission) {
            if (!\is_string($permission)) {
                throw new InputValidationException('invalid "permissionList"');
            }
        }

        return $permissionList;
    }

    /**
     * @param string $userPass
     *
     * @return string
     */
    public static function userPass($userPass)
    {
        self::requireUtf8($userPass, 'userPass');

        $userPassLength = mb_strlen($userPass);
        if (8 > $userPassLength) {
            throw new InputValidationException('password MUST be at least 8 characters long');
        }

        return $userPass;
    }

    /**
     * @param string $uiLang
     *
     * @return string
     */
    public static function uiLang($uiLang)
    {
        if (1 !== preg_match('/^[a-z]{2}_[A-Z]{2}$/', $uiLang)) {
            throw new InputValidationException('invalid "ui_lang"');
        }

        return $uiLang;
    }

    /**
     * @param string $inputString
     * @param string $inputName
     */
    private static function requireUtf8($inputString, $inputName): void
    {
        // we want valid UTF-8
        if (!mb_check_encoding($inputString, 'UTF-8')) {
            throw new InputValidationException(sprintf('invalid encoding for "%s"', $inputName));
        }
    }
}
