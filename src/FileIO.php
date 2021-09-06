<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

class FileIO
{
    public static function exists(string $filePath): bool
    {
        return file_exists($filePath);
    }

    public static function readFile(string $filePath): string
    {
        if (false === self::exists($filePath)) {
            throw new RuntimeException(sprintf('unable to find "%s"', $filePath));
        }
        if (false === $fileData = file_get_contents($filePath)) {
            throw new RuntimeException(sprintf('unable to read file "%s"', $filePath));
        }

        return $fileData;
    }

    public static function writeFile(string $filePath, string $fileData, int $mode = 0600): void
    {
        if (false === file_put_contents($filePath, $fileData)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $filePath));
        }
        if (false === chmod($filePath, $mode)) {
            throw new RuntimeException(sprintf('unable to set permissions on file "%s"', $filePath));
        }
    }

    public static function deleteFile(string $filePath): void
    {
        if (false === unlink($filePath)) {
            throw new RuntimeException(sprintf('unable to delete file "%s"', $filePath));
        }
    }

    public static function createDir(string $dirPath, int $mode = 0711): void
    {
        if (false === file_exists($dirPath)) {
            if (false === mkdir($dirPath, $mode, true)) {
                throw new RuntimeException(sprintf('unable to create directory "%s"', $dirPath));
            }
        }
    }
}
