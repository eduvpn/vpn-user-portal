<?php $this->layout('base', ['activeItem' => 'stats']); ?>
<?php $this->start('content'); ?>
    <?php if (false === $stats): ?>
        <p class="plain">
            <?=$this->t('VPN usage statistics not (yet) available. Check back after midnight.'); ?>
        </p>
    <?php else: ?>
        <h2><?=$this->t('Summary'); ?></h2>
        <p>
            <?=$this->t('These statistics were last updated on %generated_at% (%generated_at_tz%) and cover the last month.'); ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th><?=$this->t('Profile'); ?></th>
                    <th><?=$this->t('Total Traffic'); ?></th>
                    <th><?=$this->t('Total # Unique Users'); ?></th>
                    <th><?=$this->t('Highest # Concurrent Connections'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stats['profiles'] as $k => $v): ?>
                <?php if (array_key_exists($k, $idNameMapping)): ?>
                    <tr>
                        <td><span title="<?=$this->e($k); ?>"><?=$this->e($idNameMapping[$k]); ?></td>
                        <td><?=$this->e($v['total_traffic'], 'bytes_to_human'); ?></td>
                        <td><?=$this->e($v['unique_user_count']); ?></td>
                        <td><span title="<?=$this->e($v['max_concurrent_connections_time']); ?> (<?=$this->e(date('T')); ?>)"><?=$this->e($v['max_concurrent_connections']); ?></span></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    
        <h2><?=$this->t('Traffic'); ?></h2>
        <p>
            <?=$this->t('VPN traffic over the last month.'); ?>
        </p>
        <?php foreach ($stats['profiles'] as $k => $v): ?>
            <?php if (array_key_exists($k, $idNameMapping)): ?>
                <h3><?=$this->e($idNameMapping[$k]); ?></h3>
                <img class="stats" src="stats/traffic?profile_id=<?=$this->e($k); ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <h2><?=$this->t('Users'); ?></h2>
        <p>
            <?=$this->t('Number of unique users of the VPN service over the last month.'); ?>
        </p>
        <?php foreach ($stats['profiles'] as $k => $v): ?>
            <?php if (array_key_exists($k, $idNameMapping)): ?>
                <h3><?=$this->e($idNameMapping[$k]); ?></h3>
                <img class="stats" src="stats/users?profile_id=<?=$this->e($k); ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php $this->stop(); ?>
