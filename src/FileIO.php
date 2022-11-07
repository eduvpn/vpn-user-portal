<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
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

    public static function read(string $filePath): string
    {
        if (false === self::exists($filePath)) {
            throw new RuntimeException(sprintf('unable to find "%s"', $filePath));
        }
        if (false === $fileData = file_get_contents($filePath)) {
            throw new RuntimeException(sprintf('unable to read file "%s"', $filePath));
        }

        return $fileData;
    }

    public static function write(string $filePath, string $fileData): void
    {
        if (false === file_put_contents($filePath, $fileData)) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $filePath));
        }
    }

    public static function delete(string $filePath): void
    {
        if (false === unlink($filePath)) {
            throw new RuntimeException(sprintf('unable to delete file "%s"', $filePath));
        }
    }

    public static function mkdir(string $dirPath): void
    {
        if (false === file_exists($dirPath)) {
            // umask still influences the default 0777
            if (false === mkdir($dirPath, 0777, true)) {
                throw new RuntimeException(sprintf('unable to create directory "%s"', $dirPath));
            }
        }
    }
}
