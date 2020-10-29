<?php $this->layout('base', ['activeItem' => 'info', 'pageTitle' => $this->t('Info')]); ?>
<?php $this->start('content'); ?>
    <h2>CA</h2>
    <table class="tbl">
        <tbody>
            <tr><th><?=$this->t('Created'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_from']); ?></td></tr>
            <tr><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_to']); ?></td></tr>
<?php if ('RSA' !== $caInfo['ca_key_type']): ?>
            <tr><th><?=$this->t('Key Type'); ?></th><td><?=$this->e($caInfo['ca_key_type']); ?></td></tr>
<?php endif; ?>
        </tbody>
    </table>
    <h2><?=$this->t('Profiles'); ?></h2>
    <ul>
    <?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
        <li><a href="#<?=$this->e($profileId); ?>"><?=$this->e($profileConfig->displayName()); ?></a></li>
    <?php endforeach; ?>
    </ul>
    
    <?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
    <h3 id="<?=$this->e($profileId); ?>"><?=$this->e($profileConfig->displayName()); ?></h3>
    <table class="tbl">
        <tbody>
            <tr>
                <th></th>
                <td>
                        
<?php if ($profileConfig->defaultGateway()): ?>
                    <span class="plain"><?=$this->t('Default Gateway'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->clientToClient()): ?>
                    <span class="plain"><?=$this->t('Client To Client'); ?></span>
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

<?php if ($profileConfig->tlsOneThree()): ?>
                    <span class="plain"><?=$this->t('TLSv1.3'); ?></span>
<?php endif; ?>   
                </td>
            </tr>
                       
            <tr><th><?=$this->t('Profile Number'); ?></th><td><?=$this->e($profileConfig->profileNumber()); ?></td></tr>
            <tr><th><?=$this->t('Hostname'); ?></th><td><code><?=$this->e($profileConfig->hostName()); ?></code></td></tr>
            <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td><code><?=$this->e($profileConfig->range()); ?></code></td></tr>
            <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td><code><?=$this->e($profileConfig->range6()); ?></code></td></tr>
            <tr><th><?=$this->t('OpenVPN Listen Address'); ?></th><td><code><?=$this->e($profileConfig->listen()); ?></code></td></tr>
            <tr><th><?=$this->t('OpenVPN Management IP'); ?></th><td><code><?=$this->e($profileConfig->managementIp()); ?></code></td></tr>
            <tr><th><?=$this->t('TLS Protection'); ?></th><td><?=$this->e($profileConfig->tlsProtection()); ?></td></tr>

<?php if (null !== $dnsDomain = $profileConfig->dnsDomain()): ?>
            <tr><th><?=$this->t('DNS Domain'); ?></th><td><code><?=$this->e($dnsDomain); ?></code></td></tr>
<?php endif; ?>                    

<?php if (0 !== count($profileConfig->dnsDomainSearch())): ?>
            <tr><th><?=$this->t('DNS Domain Search'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->dnsDomainSearch() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>  
<?php endif; ?>

<?php if (0 !== count($profileConfig->routes())): ?>
            <tr><th><?=$this->t('Routes'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->routes() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->dns())): ?>
            <tr><th><?=$this->t('DNS Servers'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->dns() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>
<?php endif; ?>

<?php if (0 !== count($profileConfig->aclPermissionList())): ?>
            <tr><th><?=$this->t('ACL Permission List'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->aclPermissionList() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>                                        
<?php endif; ?>

<?php if (0 !== count($profileConfig->vpnProtoPorts())): ?>
            <tr><th><?=$this->t('OpenVPN Ports'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->vpnProtoPorts() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>    
<?php endif; ?>

<?php if (0 !== count($profileConfig->exposedVpnProtoPorts())): ?>
            <tr><th><?=$this->t('Exposed OpenVPN Ports'); ?></th>
            <td>
                <ul>
<?php foreach ($profileConfig->exposedVpnProtoPorts() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>   
<?php endif; ?>

<?php if (0 !== count($profileConfig->dnsSuffix())): ?>
            <tr><th><?=$this->t('DNS Suffix'); ?> <span class="warning"><?=$this->t('Legacy'); ?></span></th>
            <td>
                <ul>
<?php foreach ($profileConfig->dnsSuffix() as $route): ?>
                    <li><code><?=$this->e($route); ?></code></li>
<?php endforeach; ?>
                </ul>
            </td>
            </tr>
<?php endif; ?>
        </table>
<?php endforeach; ?>
<?php $this->stop('content'); ?>
