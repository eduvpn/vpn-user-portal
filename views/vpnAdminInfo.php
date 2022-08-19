<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var \Vpn\Portal\ServerInfo $serverInfo */?>
<?php /** @var array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList */?>
<?php /** @var array<string,null|array{rel_load_average:array<int>,load_average:array<float>,cpu_count:int}> $nodeInfoList */?>
<?php /** @var string $portalVersion */?>
<?php /** @var array<string,array<string>> $problemList */?>
<?php /** @var string $portalNumber */?>
<?php $this->layout('base', ['activeItem' => 'info', 'pageTitle' => $this->t('Info')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Server'); ?></h2>
    <table class="tbl">
        <tbody>
            <tr>
                <th><?=$this->t('Portal Number'); ?></th>
                <td><?=$this->e((string) $portalNumber); ?></td>
            </tr>
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
            <span class="error" title="<?=$this->e($nodeUrl); ?>"><?=$this->t('Offline'); ?><br><small><?=$this->t('CPU Usage:'); ?> <?=$this->t('N/A'); ?></small></span>
<?php else: ?>
<?php if ($nodeInfo['rel_load_average'][0] >= 75): ?>
            <span class="warning" title="<?=$this->e($nodeUrl); ?> [<?=implode(', ', $nodeInfo['rel_load_average']); ?>] #CPU=<?=$this->e((string) $nodeInfo['cpu_count']); ?>">
<?php else: ?>
            <span class="success" title="<?=$this->e($nodeUrl); ?> [<?=implode(', ', $nodeInfo['rel_load_average']); ?>] #CPU=<?=$this->e((string) $nodeInfo['cpu_count']); ?>">
<?php endif; ?>
            <?=$this->t('Online'); ?>
<?php if (0 === strpos($nodeUrl, 'https://')): ?>
🔒
<?php endif; ?>
                <br>
                <small>
<?=$this->t('CPU Usage:'); ?> <?=$this->e(sprintf('%d%%', $nodeInfo['rel_load_average'][0])); ?>
                </small>
            </span>
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
                        <dt><?=$this->t('Created On'); ?></dt><dd><span title="<?=$this->d($serverInfo->ca()->caCert()->validFrom()); ?>"><?=$this->d($serverInfo->ca()->caCert()->validFrom(), 'Y-m-d'); ?></span></dd>
                        <dt><?=$this->t('Expires On'); ?></dt><dd><span title="<?=$this->d($serverInfo->ca()->caCert()->validTo()); ?>"><?=$this->d($serverInfo->ca()->caCert()->validTo(), 'Y-m-d'); ?></span></dd>
                        <dt><?=$this->t('Fingerprint'); ?></dt><dd><code><?=$this->e(implode(' ', str_split($serverInfo->ca()->caCert()->fingerprint(), 4))); ?></code></dd>
                    </dl>
                </td>
            </tr>
            <tr>
                <th><?=$this->t('WireGuard'); ?></th>
                <td>
                    <dl>
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
<?php if ($profileConfig->defaultGateway()): ?>
                    <span class="plain"><?=$this->t('Default Gateway'); ?></span>
<?php endif; ?>
                </td>
            </tr>

<?php if ($profileConfig->oSupport() && $profileConfig->wSupport()) : ?>
            <tr><th><?=$this->t('Preferred Protocol'); ?></th><td>
<?php if ('openvpn' === $profileConfig->preferredProto()): ?>
                <span class="plain openvpn"><?=$this->t('OpenVPN'); ?></span>
<?php endif; ?>
<?php if ('wireguard' === $profileConfig->preferredProto()): ?>
        <span class="plain wireguard"><?=$this->t('WireGuard'); ?></span>
<?php endif; ?>
            </td></tr>
<?php endif; ?>

            <tr><th><?=$this->t('Hostname'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e($profileConfig->hostName($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
<?php if (1 !== $profileConfig->nodeCount() || 'http://localhost:41194' !== $profileConfig->nodeUrl(0)): ?>
            <tr><th><?=$this->t('Node URL'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e($profileConfig->nodeUrl($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dnsSearchDomainList())): ?>
            <tr><th><?=$this->t('DNS Search Domain(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dnsSearchDomainList() as $dnsSearchDomain): ?>
                <span class="plain"><code><?=$this->e($dnsSearchDomain); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->routeList())): ?>
            <tr><th><?=$this->t('Route(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->routeList() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->excludeRouteList())): ?>
            <tr><th><?=$this->t('Excluded Route(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->excludeRouteList() as $excludeRoute): ?>
                    <span class="plain"><code><?=$this->e($excludeRoute); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dnsServerList())): ?>
            <tr><th><?=$this->t('DNS Server(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dnsServerList() as $dnsServer): ?>
                    <span class="plain"><code><?=$this->e($dnsServer); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (null !== $aclPermissionList = $profileConfig->aclPermissionList()): ?>
            <tr><th><?=$this->t('ACL Permission List'); ?></th>
            <td>
<?php if (0 === count($aclPermissionList)): ?>
<span class="warning"><?=$this->t('No Permission(s)'); ?></span>
<?php else: ?>
<?php foreach ($aclPermissionList as $aclPermission): ?>
                    <span class="plain"><code><?=$this->e($aclPermission); ?></code></span>
<?php endforeach; ?>
            </td>
<?php endif; ?>
            </tr>
<?php endif; ?>

<?php if ($profileConfig->oSupport()):?>
        <tr><th colspan="2" class="openvpn"><?=$this->t('OpenVPN'); ?></th></tr>
<?php if ($profileConfig->oEnableLog() || $profileConfig->oBlockLan()):?>
            <tr>
                <th></th>
                <td>
<?php if ($profileConfig->oEnableLog()): ?>
                    <span class="plain"><?=$this->t('OpenVPN Server Log'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->oBlockLan()): ?>
                    <span class="plain"><?=$this->t('Block LAN'); ?></span>
<?php endif; ?>
                </td>
            </tr>
<?php endif; ?>
            <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->oRangeFour($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
            <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->oRangeSix($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
<?php if (0 !== count($profileConfig->oUdpPortList()) || 0 !== count($profileConfig->oTcpPortList())): ?>
            <tr><th><?=$this->t('Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->oUdpPortList() as $udpPort): ?>
                    <span class="plain"><code><?=$this->e(sprintf('udp/%s', $udpPort)); ?></code></span>
<?php endforeach; ?>
<?php foreach ($profileConfig->oTcpPortList() as $tcpPort): ?>
                    <span class="plain"><code><?=$this->e(sprintf('tcp/%s', $tcpPort)); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->oExposedUdpPortList()) || 0 !== count($profileConfig->oExposedTcpPortList())): ?>
            <tr><th><?=$this->t('Offered Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->oExposedUdpPortList() as $oExposedUdpPort): ?>
                    <span class="plain"><code><?=$this->e(sprintf('udp/%s', $oExposedUdpPort)); ?></code></span>
<?php endforeach; ?>
<?php foreach ($profileConfig->oExposedTcpPortList() as $oExposedTcpPort): ?>
                    <span class="plain"><code><?=$this->e(sprintf('tcp/%s', $oExposedTcpPort)); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>

<?php if ($profileConfig->wSupport()):?>
        <tr><th colspan="2" class="wireguard"><?=$this->t('WireGuard'); ?></th>
            <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->wRangeFour($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
            <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td>
<?php for ($i = 0; $i < $profileConfig->nodeCount(); ++$i): ?>
            <span class="plain"><code><?=$this->e((string) $profileConfig->wRangeSix($i)); ?></code></span>
<?php endfor; ?>
            </td></tr>
<?php endif; ?>

<?php if (array_key_exists($profileConfig->profileId(), $problemList) && 0 !== count($problemList[$profileConfig->profileId()])): ?>
            <tr>
                <th colspan="2" class="issues"><?=$this->t('Issues'); ?></th>
            </tr>
            <tr>
                <th></th>
                <td>
<?php foreach ($problemList[$profileConfig->profileId()] as $p):?>
                        <span class="warning"><?=$this->e($p); ?></span>
<?php endforeach; ?>
                </td>
            </tr>
<?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>
<?php $this->stop('content'); ?>
