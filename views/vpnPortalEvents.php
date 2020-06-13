<?php $this->layout('base', ['activeItem' => 'account', 'pageTitle' => $this->t('Account')]); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Account'); ?></h1>
    <h2><?=$this->t('Connection History'); ?></h2>
    <p>
        <?=$this->t('This is a list of your most recent VPN connections.'); ?>
    </p>
<?php if (0 === count($userConnectionLogEntries)): ?>
    <p class="plain"><?=$this->t('No connections yet.'); ?></p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr>
                <th><?=$this->t('Profile'); ?></th>
                <th><?=$this->t('Connected'); ?> (<?=$this->e(date('T')); ?>)</th>
                <th><?=$this->t('Disconnected'); ?> (<?=$this->e(date('T')); ?>)</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($userConnectionLogEntries as $logEntry): ?>
<?php if (null !== $logEntry['disconnected_at']): ?>
            <tr>
                <td title="<?=$this->e($logEntry['profile_id']); ?>">
<?php if (array_key_exists($logEntry['profile_id'], $idNameMapping)): ?>
                    <?=$this->e($idNameMapping[$logEntry['profile_id']]); ?>
<?php else: ?>
                    <?=$this->e($logEntry['profile_id']); ?>
<?php endif; ?>
                <td title="IPv4: <?=$this->e($logEntry['ip4']); ?>, IPv6: <?=$this->e($logEntry['ip6']); ?>"><?=$this->d($logEntry['connected_at']); ?></td>
                <td title="<?=$this->e((string) $logEntry['bytes_transferred'], 'bytes_to_human'); ?>"><?=$this->d($logEntry['disconnected_at']); ?></td>
            </tr>
<?php endif; ?>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

    <h2><?=$this->t('Events'); ?></h2>
    <p>
        <?=$this->t('This is a list of the most recent events for your account.'); ?>
    </p>
    <?php if (0 === count($userMessages)): ?>
        <p class="plain"><?=$this->t('No events yet.'); ?></p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Date/Time'); ?> (<?=$this->e(date('T')); ?>)</th><th><?=$this->t('Message'); ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($userMessages as $message): ?>
                    <tr>
                        <td><?=$this->d($message['date_time']); ?></td>
                        <td><?=$this->e($message['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop('content'); ?>
