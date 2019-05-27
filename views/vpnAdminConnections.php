<?php $this->layout('base', ['activeItem' => 'connections']); ?>
<?php $this->start('content'); ?>
    <?php if (0 === count($profileConfigList)): ?>
        <p class="warning"><?=$this->t('No VPN profiles configured.'); ?></p>
    <?php else: ?>
    <?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
        <h2 id="<?=$this->e($profileId); ?>"><?=$this->e($profileConfig->getDisplayName()); ?></h2>
        <?php if (0 === count($profileConnectionList[$profileId])): ?>
            <p class="plain"><?=$this->t('No clients connected.'); ?></p>
        <?php else: ?>
            <table class="tbl">
            <thead>
                <tr>
                    <th><?=$this->t('User ID'); ?></th>
                    <th><?=$this->t('Name'); ?></th>
                    <th><?=$this->t('IP address'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profileConnectionList[$profileId] as $profileConnection): ?>
                <tr>
                    <td>
                        <a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($profileConnection['user_id'], 'rawurlencode'); ?>"><?=$this->e($profileConnection['user_id']); ?></a>
                    </td>
                    <td>
                        <span title="<?=$this->e($profileConnection['common_name']); ?>"><?=$this->e($profileConnection['display_name']); ?></span>
                    </td>
                    <td>
                        <ul>
                            <?php foreach ($profileConnection['virtual_address'] as $ip): ?>
                            <li><code><?=$this->e($ip); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
<?php $this->stop(); ?>
