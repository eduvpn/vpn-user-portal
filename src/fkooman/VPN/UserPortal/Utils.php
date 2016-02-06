<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\VPN\UserPortal;

use fkooman\Http\Exception\BadRequestException;
use ZipArchive;
use DomainException;

class Utils
{
    public static function validateConfigName($configName)
    {
        if (null === $configName) {
            throw new BadRequestException('missing parameter');
        }
        if (!is_string($configName)) {
            throw new BadRequestException('malformed parameter');
        }
        if (32 < strlen($configName)) {
            throw new BadRequestException('name too long, maximum 32 characters');
        }
        // XXX: be less restrictive in supported characters...
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $configName)) {
            throw new BadRequestException('invalid characters in name');
        }
    }

    public static function configToZip($configName, $configData)
    {
        $defaultCertKeyFileNames = array(
            'ca' => 'ca.crt',
            'cert' => 'client.crt',
            'key' => 'client.key',
            'tls-auth' => 'ta.key',
        );

        $certKeyFileNames = array(
            'ca' => sprintf('%s_ca.crt', $configName),
            'cert' => sprintf('%s_client.crt', $configName),
            'key' => sprintf('%s_client.key', $configName),
            'tls-auth' => sprintf('%s_ta.key', $configName),
        );

        $zipName = tempnam(sys_get_temp_dir(), 'vup_');
        $z = new ZipArchive();
        $z->open($zipName, ZipArchive::CREATE);

        foreach (array('cert', 'ca', 'key', 'tls-auth') as $inlineType) {
            // replace the inline rules with actual file names
            $configData = str_replace(
                sprintf('#%s %s', $inlineType, $defaultCertKeyFileNames[$inlineType]),
                sprintf('%s %s', $inlineType, $certKeyFileNames[$inlineType]),
                $configData
            );

            // remove the inline blocks
            $pattern = sprintf('/\<%s\>(.*)\<\/%s\>/msU', $inlineType, $inlineType);
            if (1 !== preg_match($pattern, $configData, $matches)) {
                throw new DomainException('inline type not found');
            }
            $configData = preg_replace(
                $pattern,
                '',
                $configData
            );

            // add the file to the zip
            $z->addFromString($certKeyFileNames[$inlineType], trim($matches[1]));
        }

        // remove key-direction
        $configData = str_replace('key-direction 1', '', $configData);

        // add the ovpn config to the ZIP
        $z->addFromString(sprintf('%s.ovpn', $configName), trim($configData));
        $z->close();
        $zipData = file_get_contents($zipName);
        unlink($zipName);

        return $zipData;
    }
}
