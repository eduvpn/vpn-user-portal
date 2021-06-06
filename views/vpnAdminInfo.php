<?php declare(strict_types=1); ?>
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
                <th><?=$this->t('Profiles'); ?></th>
                <td>
                    <ul>
<?php foreach ($profileConfigList as $profileConfig): ?>
                        <li><a href="#<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></a></li>
<?php endforeach; ?>
                    </ul>
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
                        <dt><?=$this->t('Created'); ?></dt><dd><?=$this->d($caInfo->validFrom()->format(DateTimeImmutable::ATOM)); ?></dd>
                        <dt><?=$this->t('Expires'); ?></dt><dd><?=$this->d($caInfo->validTo()->format(DateTimeImmutable::ATOM)); ?></dd>
                        <dt><?=$this->t('Fingerprint'); ?></dt><dd><code><?=$this->e($caInfo->fingerprint(true)); ?></code></dd>
                    </dl>
                </td>
            </tr>
            <tr>
                <th><?=$this->t('WireGuard'); ?></th>
                <td>
                    <dl>
                        <dt><?=$this->t('Public Key'); ?></dt><dd><code>${WireGuard Public Key}</code></dd>
                    </dl>
                </td>
            </tr>
            <tr>
                <th><?=$this->t('OAuth'); ?></th>
                <td>
                    <dl>
                        <dt><?=$this->t('Public Key'); ?></dt><dd><code><?=$this->e($oauthPublicKey->encode()); ?></code></dd>
                    </dl>
                </td>
            </tr>
        </tbody>
    </table>
    </details>

    <?php foreach ($profileConfigList as $profileConfig): ?>
    <h2 id="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></h2>
    <table class="tbl">
        <tbody>
            <tr>
                <th></th>
                <td>
<?php if ('openvpn' === $profileConfig->vpnType()): ?>
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

<?php if ($profileConfig->enableLog()): ?>
                    <span class="plain"><?=$this->t('Enable Log'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->enableAcl()): ?>
                    <span class="plain"><?=$this->t('Enable ACL'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->hideProfile()): ?>
                    <span class="plain"><?=$this->t('Hide Profile'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->blockLan()): ?>
                    <span class="plain"><?=$this->t('Block LAN'); ?></span>
<?php endif; ?>
                </td>
            </tr>

            <tr><th><?=$this->t('Profile Number'); ?></th><td><?=$this->e((string) $profileConfig->profileNumber()); ?></td></tr>
            <tr><th><?=$this->t('Hostname'); ?></th><td><code><?=$this->e($profileConfig->hostName()); ?></code></td></tr>
            <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td><code><?=$this->e($profileConfig->range()); ?></code></td></tr>
            <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td><code><?=$this->e($profileConfig->range6()); ?></code></td></tr>
<?php if ('::' !== $listenIp = $profileConfig->listenIp()): ?>
            <tr><th><?=$this->t('Listen IP'); ?></th><td><code><?=$this->e($listenIp); ?></code></td></tr>
<?php endif; ?>
<?php if ('127.0.0.1' !== $nodeIp = $profileConfig->nodeIp()): ?>
            <tr><th><?=$this->t('Node IP'); ?></th><td><code><?=$this->e($nodeIp); ?></code></td></tr>
<?php endif; ?>

<?php if (null !== $dnsDomain = $profileConfig->dnsDomain()): ?>
            <tr><th><?=$this->t('DNS Domain'); ?></th><td><code><?=$this->e($dnsDomain); ?></code></td></tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dnsDomainSearch())): ?>
            <tr><th><?=$this->t('DNS Search Domain(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dnsDomainSearch() as $route): ?>
                <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->routes())): ?>
            <tr><th><?=$this->t('Route(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->routes() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dns())): ?>
            <tr><th><?=$this->t('DNS Server(s)'); ?></th>
            <td>
<?php foreach ($profileConfig->dns() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->aclPermissionList())): ?>
            <tr><th><?=$this->t('ACL Permission List'); ?></th>
            <td>
<?php foreach ($profileConfig->aclPermissionList() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnType()): ?>
<?php if (0 !== count($profileConfig->vpnProtoPorts())): ?>
            <tr><th><?=$this->t('Protocols/Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->vpnProtoPorts() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>

<?php if ('openvpn' === $profileConfig->vpnType()): ?>
<?php if (0 !== count($profileConfig->exposedVpnProtoPorts())): ?>
            <tr><th><?=$this->t('Offered Protocols/Ports'); ?></th>
            <td>
<?php foreach ($profileConfig->exposedVpnProtoPorts() as $route): ?>
                    <span class="plain"><code><?=$this->e($route); ?></code></span>
<?php endforeach; ?>
            </td>
            </tr>
<?php endif; ?>
<?php endif; ?>
        </table>
<?php endforeach; ?>
<?php $this->stop('content'); ?>
