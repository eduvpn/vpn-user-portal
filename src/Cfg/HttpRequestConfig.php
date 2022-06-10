<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Cfg;

use Vpn\Portal\Ip;

class HttpRequestConfig
{
    use ConfigTrait;

    private array $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * @return array<Ip>
     */
    public function proxyList(): array
    {
        $proxyList = [];
        foreach ($this->requireStringArray('proxyList', []) as $proxyRange) {
            $range = Ip::fromIpPrefix($proxyRange);
            $proxyList[] = $range;
        }
        return $proxyList;
    }

    public function proxySchemeHeader(): string
    {
        return $this->requireHttpHeader('proxySchemeHeader', 'X-Forwarded-Proto');
    }

    public function proxyHostHeader(): string
    {
        return $this->requireHttpHeader('proxyHostHeader', 'X-Forwarded-Host');
    }

    public function proxyPortHeader(): string
    {
        return $this->requireHttpHeader('proxyPortHeader', 'X-Forwarded-Port');
    }

    private function requireHttpHeader($configField, $default): string
    {
        $header = $this->requireString($configField, $default);

        // Translate the header into the key that is used in the serverdata.
        // See: RFC 3875, 4.1.18: "The HTTP header field name is converted to
        // upper case, has all occurrences of "-" replaced with "_" and has
        // "HTTP_" prepended to give the meta-variable name."
        $serverDataKey = 'HTTP_' . strtr(strtoupper($header), '-', '_');

        return $serverDataKey;
    }
}
