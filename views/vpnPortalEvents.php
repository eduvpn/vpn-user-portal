<?php $this->layout('base', ['activeItem' => 'account', 'pageTitle' => $this->t('Account')]); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Account'); ?></h1>
    <h2><?=$this->t('Connection History'); ?></h2>
    <p>
        <?=$this->t('This is a list of VPN connections with your account.'); ?>
    </p>
<?php if (0 === count($userConnectionLogEntries)): ?>
    <p class="plain"><?=$this->t('No connections yet.'); ?></p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr>
                <th><?=$this->t('Profile'); ?></th>
                <th><?=$this->t('IPs'); ?></th>
                <th><?=$this->t('Connected'); ?> (<?=$this->e(date('T')); ?>)</th>
                <th><?=$this->t('Disconnected'); ?> (<?=$this->e(date('T')); ?>)</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($userConnectionLogEntries as $logEntry): ?>
            <tr>
                <td><?=$this->e($logEntry['profile_id']); ?></td>
                <td>
                    <ul>
                        <li><?=$this->e($logEntry['ip4']); ?></li>
                        <li><?=$this->e($logEntry['ip6']); ?></li>
                    </ul>
                </td>
                <td><?=$this->d($result['connected_at']); ?></td>
                <td>
                    <?php if ($result['disconnected_at']): ?>
                        <?=$this->d($result['disconnected_at']); ?>
                    <?php else: ?>
                        <em><?=$this->t('N/A'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

    <h2><?=$this->t('Events'); ?></h2>
    <p>
        <?=$this->t('This is a list of events that occurred related to your account.'); ?>
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
