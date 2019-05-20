<?php $this->layout('base', ['activeItem' => 'stats']); ?>
<?php $this->start('content'); ?>
<h2><?=$this->t('Summary'); ?></h2>
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
    <?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
        <tr>
            <td><span title="<?=$this->e($profileId); ?>"><?=$this->e($profileConfig->getDisplayName()); ?></td>
            <?php if (array_key_exists($profileId, $statsData)): ?>
                <td><?=$this->e($statsData[$profileId]['total_traffic'], 'bytes_to_human'); ?></td>
                <td><?=$this->e($statsData[$profileId]['unique_user_count']); ?></td>
                <td><span title="<?=$this->e($statsData[$profileId]['max_concurrent_connections_time']); ?> (<?=$this->e(date('T')); ?>)"><?=$this->e($statsData[$profileId]['max_concurrent_connections']); ?></span></td>
            <?php else: ?>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php $this->stop(); ?>
