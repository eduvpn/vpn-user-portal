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
    <ul class="profileList">
    <?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
        <li>
        <details>
            <summary><?=$this->e($profileConfig->displayName()); ?></summary>
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
                            <span class="plain"><?=$this->t('Logging'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->enableAcl()): ?>
                            <span class="plain"><?=$this->t('Access Control'); ?></span>
<?php endif; ?>

<?php if ($profileConfig->hideProfile()): ?>
                            <span class="plain"><?=$this->t('Hidden Profile'); ?></span>
<?php endif; ?>                    

<?php if ($profileConfig->blockLan()): ?>
                            <span class="plain"><?=$this->t('LAN Traffic Blocked'); ?></span>
<?php endif; ?>     

<?php if ($profileConfig->tlsOneThree()): ?>
                            <span class="plain"><?=$this->t('TLS >= 1.3'); ?></span>
<?php endif; ?>   
                        </td>
                    </tr>
                               
                    <tr><th><?=$this->t('Profile Number'); ?></th><td><?=$this->e($profileConfig->profileNumber()); ?></td></tr>
                    <tr><th><?=$this->t('Hostname'); ?></th><td><?=$this->e($profileConfig->hostName()); ?></td></tr>
                    <tr><th><?=$this->t('IPv4 Prefix'); ?></th><td><?=$this->e($profileConfig->range()); ?></td></tr>
                    <tr><th><?=$this->t('IPv6 Prefix'); ?></th><td><?=$this->e($profileConfig->range6()); ?></td></tr>
                    <tr><th><?=$this->t('Listen Address'); ?></th><td><?=$this->e($profileConfig->listen()); ?></td></tr>
                    <tr><th><?=$this->t('Management IP'); ?></th><td><?=$this->e($profileConfig->managementIp()); ?></td></tr>
                    <tr><th><?=$this->t('TLS Protection'); ?></th><td><?=$this->e($profileConfig->tlsProtection()); ?></td></tr>
<?php if (null !== $dnsDomain = $profileConfig->dnsDomain()): ?>
                    <tr><th><?=$this->t('DNS Domain'); ?></th><td><?=$this->e($dnsDomain); ?></td></tr>
<?php else: ?>
                    <tr><th><?=$this->t('DNS Domain'); ?></th><td><em><?=$this->t('N/A'); ?></em></td></tr>
<?php endif; ?>                    

                    <tr><th><?=$this->t('DNS Domain Search'); ?></th>
<?php if (0 !== count($profileConfig->dnsDomainSearch())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->dnsDomainSearch() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>  
                    
                    <tr><th><?=$this->t('Routes'); ?></th>
<?php if (0 !== count($profileConfig->routes())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->routes() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>

                    <tr><th><?=$this->t('DNS Servers'); ?></th>
<?php if (0 !== count($profileConfig->dns())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->dns() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>

                    <tr><th><?=$this->t('ACL Permission List'); ?></th>
<?php if (0 !== count($profileConfig->aclPermissionList())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->aclPermissionList() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>                                        

                    <tr><th><?=$this->t('VPN Ports'); ?></th>
<?php if (0 !== count($profileConfig->vpnProtoPorts())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->vpnProtoPorts() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>    

                    <tr><th><?=$this->t('Exposed VPN Ports'); ?></th>
<?php if (0 !== count($profileConfig->exposedVpnProtoPorts())): ?>
                    <td>
                        <ul>
<?php foreach ($profileConfig->exposedVpnProtoPorts() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php else: ?>
                    <td><em><?=$this->t('N/A'); ?></em></td>
<?php endif; ?>
                    </tr>   

<?php if (0 !== count($profileConfig->dnsSuffix())): ?>
                    <tr><th><?=$this->t('DNS Suffix');?> <span class="warning"><?=$this->t('legacy');?></span></th>
                    <td>
                        <ul>
<?php foreach ($profileConfig->dnsSuffix() as $route): ?>
                            <li><?=$this->e($route); ?></li>
<?php endforeach; ?>
                        </ul>
                    </td>
<?php endif; ?>
            </table>
        </details></li>
    <?php endforeach; ?>
    </ul>
<?php $this->stop('content'); ?>
