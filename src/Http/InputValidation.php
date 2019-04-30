<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http;

use DateTime;
use LC\Portal\Http\Exception\InputValidationException;
use LC\Portal\Json;

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

        $displayName = filter_var($displayName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

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
     * @param string $totpSecret
     *
     * @return string
     */
    public static function totpSecret($totpSecret)
    {
        if (1 !== preg_match('/^[A-Z0-9]{32}$/', $totpSecret)) {
            throw new InputValidationException('invalid "totp_secret"');
        }

        return $totpSecret;
    }

    /**
     * @param string $totpKey
     *
     * @return string
     */
    public static function totpKey($totpKey)
    {
        if (1 !== preg_match('/^[0-9]{6}$/', $totpKey)) {
            throw new InputValidationException('invalid "totp_key"');
        }

        return $totpKey;
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
     * @return \DateTime
     */
    public static function dateTime($dateTime)
    {
        if (false === $dateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime)) {
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
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InputValidationException('invalid "ip_address"');
        }

        // normalize the IP address (only makes a difference for IPv6)
        return inet_ntop(inet_pton($ipAddress));
    }

    /**
     * @param string $ip4
     *
     * @return string
     */
    public static function ip4($ip4)
    {
        if (false === filter_var($ip4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InputValidationException('invalid "ip4"');
        }

        return $ip4;
    }

    /**
     * @param string $ip6
     *
     * @return string
     */
    public static function ip6($ip6)
    {
        if (false === filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new InputValidationException('invalid "ip6"');
        }

        // normalize the IPv6 address
        return inet_ntop(inet_pton($ip6));
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
     * @param string $twoFactorType
     *
     * @return string
     */
    public static function twoFactorType($twoFactorType)
    {
        if ('totp' !== $twoFactorType) {
            throw new InputValidationException('invalid "two_factor_type"');
        }

        return $twoFactorType;
    }

    /**
     * @param string $twoFactorValue
     *
     * @return string
     */
    public static function twoFactorValue($twoFactorValue)
    {
        if (0 >= \strlen($twoFactorValue)) {
            throw new InputValidationException('invalid "two_factor_value"');
        }

        return $twoFactorValue;
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
     * @return \DateTime
     */
    public static function expiresAt($expiresAt)
    {
        if (false === $dateTime = DateTime::createFromFormat(DateTime::ATOM, $expiresAt)) {
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
     * @param string $inputString
     * @param string $inputName
     *
     * @return void
     */
    private static function requireUtf8($inputString, $inputName)
    {
        // we want valid UTF-8
        if (!mb_check_encoding($inputString, 'UTF-8')) {
            throw new InputValidationException(sprintf('invalid encoding for "%s"', $inputName));
        }
    }
}
