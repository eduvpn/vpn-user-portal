<?php $this->layout('base', ['activeItem' => 'connections']); ?>
<?php $this->start('content'); ?>
    <?php foreach ($connections as $profile): ?>
        <h2 id="<?=$this->e($profile['id']); ?>"><?=$this->e($idNameMapping[$profile['id']]); ?></h2>
        <?php if (0 === count($profile['connections'])): ?>
            <p class="plain"><?=$this->t('No clients connected.'); ?></p>
        <?php else: ?>
            <table>
            <thead>
                <tr>
                    <th><?=$this->t('User ID'); ?></th>
                    <th><?=$this->t('Name'); ?></th>
                    <th><?=$this->t('IP address'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profile['connections'] as $connection): ?>
                <tr>
                    <td>
                        <a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($connection['user_id'], 'rawurlencode'); ?>"><?=$this->e($connection['user_id']); ?></a>
                    </td>
                    <td>
                        <span title="<?=$this->e($connection['common_name']); ?>"><?=$this->e($connection['display_name']); ?></span>
                    </td>
                    <td>
                        <ul class="simple">
                            <?php foreach ($connection['virtual_address'] as $ip): ?>
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
<?php $this->stop(); ?>
