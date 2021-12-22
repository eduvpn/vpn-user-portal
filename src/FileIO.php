<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use RuntimeException;

class FileIO
{
    public static function exists(string $filePath): bool
    {
        return file_exists($filePath);
    }

    public static function readFileIfExists(string $filePath): ?string
    {
        if (!self::exists($filePath)) {
            return null;
        }

        return self::readFile($filePath);
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

    public static function writeFile(string $filePath, string $fileData): void
    {
        // XXX touch, chmod, write to avoid racing condition?
        if (false === file_put_contents($filePath, $fileData)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $filePath));
        }
        if (false === chmod($filePath, 0600)) {
            throw new RuntimeException(sprintf('unable to set permissions on file "%s"', $filePath));
        }
    }

    public static function deleteFile(string $filePath): void
    {
        if (false === unlink($filePath)) {
            throw new RuntimeException(sprintf('unable to delete file "%s"', $filePath));
        }
    }

    public static function createDir(string $dirPath): void
    {
        if (false === file_exists($dirPath)) {
            if (false === mkdir($dirPath, 0700, true)) {
                throw new RuntimeException(sprintf('unable to create directory "%s"', $dirPath));
            }
        }
    }
}
