<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

class FileIO
{
    /**
     * @param string $filePath
     *
     * @return bool
     */
    public static function exists($filePath)
    {
        return file_exists($filePath);
    }

    /**
     * @param string $filePath
     *
     * @return string
     */
    public static function readFile($filePath)
    {
        if (false === self::exists($filePath)) {
            throw new RuntimeException(sprintf('unable to find "%s"', $filePath));
        }
        if (false === $fileData = file_get_contents($filePath)) {
            throw new RuntimeException(sprintf('unable to read file "%s"', $filePath));
        }

        return $fileData;
    }

    /**
     * @param string $filePath
     *
     * @return array
     */
    public static function readJsonFile($filePath)
    {
        $fileData = self::readFile($filePath);

        return Json::decode($fileData);
    }

    /**
     * @param string $filePath
     * @param string $fileData
     * @param int    $mode
     *
     * @return void
     */
    public static function writeFile($filePath, $fileData, $mode = 0600)
    {
        if (false === file_put_contents($filePath, $fileData)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $filePath));
        }
        if (false === chmod($filePath, $mode)) {
            throw new RuntimeException(sprintf('unable to set permissions on file "%s"', $filePath));
        }
    }

    /**
     * @param string $filePath
     * @param array  $fileJsonData
     * @param int    $mode
     *
     * @return void
     */
    public static function writeJsonFile($filePath, array $fileJsonData, $mode = 0600)
    {
        $fileData = Json::encode($fileJsonData);

        self::writeFile($filePath, $fileData, $mode);
    }

    /**
     * @param string $filePath
     *
     * @return void
     */
    public static function deleteFile($filePath)
    {
        if (false === unlink($filePath)) {
            throw new RuntimeException(sprintf('unable to delete file "%s"', $filePath));
        }
    }

    /**
     * @param string $dirPath
     * @param int    $mode
     *
     * @return void
     */
    public static function createDir($dirPath, $mode = 0711)
    {
        if (false === file_exists($dirPath)) {
            if (false === mkdir($dirPath, $mode, true)) {
                throw new RuntimeException(sprintf('unable to create directory "%s"', $dirPath));
            }
        }
    }
}
