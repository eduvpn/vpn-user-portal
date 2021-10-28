<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var \LC\Portal\ServerInfo $serverInfo */?>
<?php /** @var array<\LC\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<string,null|array{load_average:array<float>,cpu_count:int}> $nodeInfoList */?>
<?php /** @var string $portalVersion */?>
<?php $this->layout('base', ['activeItem' => 'info', 'pageTitle' => $this->t('Info')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Server'); ?></h2>
    <table class="tbl">
        <tbody>
            <tr>
                <th><?=$this->t('Version'); ?></th>
                <td>v<?=$this->e($portalVersion); ?></td>
            </tr>
            <tr>
                <th><?=$this->t('Profile(s)'); ?></th>
                <td>
                    <ul>
<?php foreach ($profileConfigList as $profileConfig): ?>
                        <li><a href="#<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></a></li>
<?php endforeach; ?>
                    </ul>
                </td>
            </tr>

            <tr>
                <th><?=$this->t('Node(s)'); ?></th>
                <td>
<?php foreach ($nodeInfoList as $nodeUrl => $nodeInfo): ?>
<?php if (null === $nodeInfo): ?>
            <span class="error" title="<?=$this->e($nodeUrl); ?>"><?=$this->t('Offline'); ?><br><small><?=$this->t('N/A'); ?></small></span>
<?php else: ?>
            <span class="success" title="<?=$this->e($nodeUrl); ?>"><?=$this->t('Online'); ?><br><small><?=$this->e(implode(', ', $nodeInfo['load_average'])); ?></small></span>
<?php endif; ?>
<?php endforeach; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <details><summary><?=$this->t('More'); ?></summary>
    <table class="tbl">
        <tbody>
            <tr>
                <th><?=$this->t('CA'); ?></th>
                <td>
                    <dl>
                        <dt><?=$this->t('Created'); ?></dt><dd><?=$this->d($serverInfo->ca()->caCert()->validFrom()); ?></dd>
                        <dt><?=$this->t('Expires'); ?></dt><dd><?=$this->d($serverInfo->ca()->caCert()->validTo()); ?></dd>
                        <dt><?=$this->t('Fingerprint'); ?></dt><dd><code><?=$this->e(implode(' ', str_split($serverInfo->ca()->caCert()->fingerprint(), 4))); ?></code></dd>
                    </dl>
                </td>
            </tr>
            <tr>
                <th><?=$this->t('WireGuard'); ?></th>
                <td>
                    <dl>
                        <dt><?=$this->t('Public Key'); ?></dt><dd><code><?=$this->e($serverInfo->wgPublicKey()); ?></code></dd>
                        <dt><?=$this->t('Port'); ?></dt><dd><code><?=$this->e((string) $serverInfo->wgPort()); ?></code></dd>
                    </dl>
                </td>
            </tr>
            <tr>
                <th><?=$this->t('OAuth'); ?></th>
                <td>
                    <dl>
                        <dt><?=$this->t('Public Key'); ?></dt><dd><code><?=$this->e($serverInfo->oauthPublicKey()); ?></code></dd>
                    </dl>
                </td>
            </tr>
        </tbody>
    </table>
    </details>

    <h2><?=$this->t('Profile(s)'); ?></h2>
<?php foreach ($profileConfigList as $profileConfig): ?>
    <h3 id="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></h3>
    <table class="tbl">
        <tbody>
            <tr>
                <th></th>
                <td>
<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
        <span class="plain"><?=$this->t('OpenVPN'); ?></span>
<?php else: ?>
        <span class="plain"><?=$this->t('WireGuard'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->defaultGateway()): ?>
                    <span class="plain"><?=$this->t('Default Gateway'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->clientToClient()): ?>
                    <span class="plain"><?=$this->t('Client-to-client'); ?></span>
    <?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if ($profileConfig->enableLog()): ?>
                    <span class="plain"><?=$this->t('OpenVPN Server Log'); ?></span>
<?php endif; ?>
<?php endif; ?>

<?php if ($profileConfig->enableAcl()): ?>
                    <span class="plain"><?=$this->t('ACL'); ?></span>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if ($profileConfig->blockLan()): ?>
                    <span class="plain"><?=$this->t('Block LAN'); ?></span>
<?php endif; ?>
<?php endif; ?>
                </td>
            </tr>

            <tr><th><?=$this->t('Hostname'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e($profileConfig->hostName($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>

            <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->range($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>

            <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->range6($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>

            <tr><th><?=$this->t('Node URL'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e($profileConfig->nodeUrl($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>

<?php if (null !== $dnsDomain = $profileConfig->dnsDomain()): ?>
            <tr><th><?=$this->t('DNS Domain'); ?></th><td><code><?=$this->e($dnsDomain); ?></code></td></tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dnsDomainSearch())): ?>
            <tr><th><?=$this->t('DNS Search Domain(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dnsDomainSearch() as $dnsDomain): ?>
                <span class="plain"><code><?=$this->e($dnsDomain); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->tunnelRouteList())): ?>
            <tr><th><?=$this->t('Route(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->tunnelRouteList() as $tunnelRoute): ?>
                    <span class="plain"><code><?=$this->e($tunnelRoute); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dns())): ?>
            <tr><th><?=$this->t('DNS Server(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dns() as $dnsAddress): ?>
                    <span class="plain"><code><?=$this->e($dnsAddress); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->aclPermissionList())): ?>
            <tr><th><?=$this->t('ACL Permission List'); ?></th>
            <td>
<?php foreach ($profileConfig->aclPermissionList() as $aclPermission): ?>
                    <span class="plain"><code><?=$this->e($aclPermission); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if (0 !== count($profileConfig->udpPortList())): ?>
            <tr><th><?=$this->t('UDP Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->udpPortList() as $udpPort): ?>
                    <span class="plain"><code><?=$this->e((string) $udpPort); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if (0 !== count($profileConfig->tcpPortList())): ?>
            <tr><th><?=$this->t('TCP Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->tcpPortList() as $tcpPort): ?>
                    <span class="plain"><code><?=$this->e((string) $tcpPort); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if (0 !== count($profileConfig->exposedUdpPortList())): ?>
            <tr><th><?=$this->t('Offered UDP Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->exposedUdpPortList() as $exposedUdpPort): ?>
                    <span class="plain"><code><?=$this->e((string) $exposedUdpPort); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnProto()): ?>
<?php if (0 !== count($profileConfig->exposedTcpPortList())): ?>
            <tr><th><?=$this->t('Offered TCP Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->exposedTcpPortList() as $exposedTcpPort): ?>
                    <span class="plain"><code><?=$this->e((string) $exposedTcpPort); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>
<?php $this->stop('content'); ?>
