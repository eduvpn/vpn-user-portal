<?php declare(strict_types=1);
$this->layout('base', ['activeItem' => 'stats']); ?>
<?php $this->start('content'); ?>
<table class="tbl">
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
            <td><span title="<?=$this->e($profileId); ?>"><?=$this->e($profileConfig->getDisplayName()); ?></span></td>
            <?php if (array_key_exists($profileId, $statsData)): ?>
                <td><?=$this->e((string) $statsData[$profileId]['total_traffic'], 'bytes_to_human'); ?></td>
                <td><?=$this->e((string) $statsData[$profileId]['unique_user_count']); ?></td>
                <td><span title="<?=$this->e((string) $statsData[$profileId]['max_concurrent_connections_time']); ?> (<?=$this->e(date('T')); ?>)"><?=$this->e((string) $statsData[$profileId]['max_concurrent_connections']); ?> (<?=$this->e((string) $maxConcurrentConnectionLimit[$profileId]); ?>)</span></td>
            <?php else: ?>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php foreach ($profileConfigList as $profileId => $profileConfig): ?>
<?php if (array_key_exists($profileId, $graphStats) && 0 !== count($graphStats[$profileId])): ?>
    <h3><?=$profileConfig->getDisplayName(); ?></h3>
    <!-- #users -->
    <table class="stats stats-users">
        <thead>
            <tr><th><?=$this->t('Date'); ?></th><th><?=$this->t('# Users'); ?></th></tr>
        </thead>
        <tbody>
<?php foreach ($graphStats[$profileId] as $day => $dayInfo): ?>
            <tr>
                <th><?=$this->e($day); ?></th><td><span><?=str_repeat('X', $dayInfo['user_fraction']); ?></span></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endforeach; ?>
<?php $this->stop(); ?>
