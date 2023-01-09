<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Cfg\ProfileConfig;
use Vpn\Portal\OpenVpn\ServerConfig;
use Vpn\Portal\OpenVpn\TlsCrypt;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServerConfigTest extends TestCase
{
    public function testDnsTemplate(): void
    {
        $tmpDir = sprintf('%s/vpn-user-portal-%s', sys_get_temp_dir(), bin2hex(random_bytes(32)));
        mkdir($tmpDir);
        copy(\dirname(__DIR__).'/data/tls-crypt-default.key', $tmpDir.'/tls-crypt-default.key');

        $s = new ServerConfig(
            new TestCa(),
            new TlsCrypt($tmpDir)
        );

        $this->assertSame(
            [
                'default-0.conf' =>
<<<EOF
    # OpenVPN Server Config | Automatically Generated | Do NOT modify!
    verb 3
    dev-type tun
    user openvpn
    group openvpn
    topology subnet
    persist-key
    persist-tun
    remote-cert-tls client
    dh none
    tls-version-min 1.3
    data-ciphers CHACHA20-POLY1305:AES-256-GCM
    reneg-sec 36000
    client-connect /usr/libexec/vpn-server-node/client-connect
    client-disconnect /usr/libexec/vpn-server-node/client-disconnect
    server 10.42.42.0/25 255.255.255.128
    server-ipv6 fd42::/112
    max-clients 124
    keepalive 10 60
    script-security 2
    dev tun0
    port 1194
    management /run/openvpn-server/default-0.sock unix
    setenv PROFILE_ID default
    proto udp6
    <ca>
    ---CA---
    </ca>
    <cert>
    ---SERVER CERT---
    </cert>
    <key>
    ---SERVER KEY---
    </key>
    <tls-crypt>
    #
    # 2048 bit OpenVPN static key
    #
    -----BEGIN OpenVPN Static key V1-----
    e543cbbac3c2b960733610f5d0b979c9
    be6b977330e441a39b46052abe241374
    a2a90343537bc843d9c8ba53c6ae6bd7
    a0916c54a443dcdfed86d0657f7a7730
    2840020d826351b02eb366c13b001de5
    29efad7eb4cacf581af15bf03e801e4a
    31317373b0375c05ba0a15a0112407ae
    30b2d12616e9edc673bf48cbd0775c02
    8e327dbc9053de448336e43c3f7b50c1
    adfac03d576b3f15eb65177f4a91e474
    315a3f1c229003a4ad8a337d15fa0232
    0dfb64bb77707091934c65ffd72f16c2
    55123c36cbf3f7d7aadffc38900ac589
    ac89924a4298aed37f1c5ee9b08ac8ff
    8c94e5a8cf8dd61882ac70af7b36e3a7
    ff80428802f089afc206f3b4e67105ec
    -----END OpenVPN Static key V1-----
    </tls-crypt>
    log /dev/null
    explicit-exit-notify 1
    push "explicit-exit-notify 1"
    push "redirect-gateway def1 ipv6"
    push "route 0.0.0.0 0.0.0.0"
    push "dhcp-option DNS 10.42.42.1"
    push "dhcp-option DNS 9.9.9.9"
    push "dhcp-option DNS fd42::1"
    push "block-outside-dns"
    EOF,
                'default-1.conf' =>
<<<EOF
    # OpenVPN Server Config | Automatically Generated | Do NOT modify!
    verb 3
    dev-type tun
    user openvpn
    group openvpn
    topology subnet
    persist-key
    persist-tun
    remote-cert-tls client
    dh none
    tls-version-min 1.3
    data-ciphers CHACHA20-POLY1305:AES-256-GCM
    reneg-sec 36000
    client-connect /usr/libexec/vpn-server-node/client-connect
    client-disconnect /usr/libexec/vpn-server-node/client-disconnect
    server 10.42.42.128/25 255.255.255.128
    server-ipv6 fd42::1:0/112
    max-clients 124
    keepalive 10 60
    script-security 2
    dev tun1
    port 1195
    management /run/openvpn-server/default-1.sock unix
    setenv PROFILE_ID default
    proto udp6
    <ca>
    ---CA---
    </ca>
    <cert>
    ---SERVER CERT---
    </cert>
    <key>
    ---SERVER KEY---
    </key>
    <tls-crypt>
    #
    # 2048 bit OpenVPN static key
    #
    -----BEGIN OpenVPN Static key V1-----
    e543cbbac3c2b960733610f5d0b979c9
    be6b977330e441a39b46052abe241374
    a2a90343537bc843d9c8ba53c6ae6bd7
    a0916c54a443dcdfed86d0657f7a7730
    2840020d826351b02eb366c13b001de5
    29efad7eb4cacf581af15bf03e801e4a
    31317373b0375c05ba0a15a0112407ae
    30b2d12616e9edc673bf48cbd0775c02
    8e327dbc9053de448336e43c3f7b50c1
    adfac03d576b3f15eb65177f4a91e474
    315a3f1c229003a4ad8a337d15fa0232
    0dfb64bb77707091934c65ffd72f16c2
    55123c36cbf3f7d7aadffc38900ac589
    ac89924a4298aed37f1c5ee9b08ac8ff
    8c94e5a8cf8dd61882ac70af7b36e3a7
    ff80428802f089afc206f3b4e67105ec
    -----END OpenVPN Static key V1-----
    </tls-crypt>
    log /dev/null
    explicit-exit-notify 1
    push "explicit-exit-notify 1"
    push "redirect-gateway def1 ipv6"
    push "route 0.0.0.0 0.0.0.0"
    push "dhcp-option DNS 10.42.42.129"
    push "dhcp-option DNS 9.9.9.9"
    push "dhcp-option DNS fd42::1:1"
    push "block-outside-dns"
    EOF,
            ],
            $s->getProfile(
                new ProfileConfig(
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => 'vpn.example.org',
                        'oRangeFour' => '10.42.42.0/24',
                        'oRangeSix' => 'fd42::/64',
                        'oUdpPortList' => [1194,1195],
                        'oTcpPortList' => [],
                        'dnsServerList' => ['@GW4@', '9.9.9.9', '@GW6@'],
                    ],
                ),
                0,
                false
            )
        );
    }
}
