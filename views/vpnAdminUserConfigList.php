<?php $this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Users'); ?></h1>
    <p>
        <?=$this->t('Managing user <code>%userId%</code>.'); ?>
    </p>

    <?php if ($isSelf): ?>
        <p class="warning"><?=$this->t('You cannot manage your own user account.'); ?></p>
    <?php endif; ?>

    <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>user">
        <fieldset>
            <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
            <?php if (!$isSelf): ?>
                <?php if ($isDisabled): ?>
                    <button name="user_action" value="enableUser"><?=$this->t('Enable User'); ?></button>
                <?php else: ?>
                    <button class="warning" name="user_action" value="disableUser"><?=$this->t('Disable User'); ?></button>
                <?php endif; ?>
                <?php if ($hasTotpSecret): ?>
                    <button class="warning" name="user_action" value="deleteTotpSecret"><?=$this->t('Delete TOTP Secret'); ?></button>
                <?php endif; ?>
            <?php endif; ?>
        </fieldset>
    </form>

    <h2><?=$this->t('Certificates'); ?></h2>

    <?php if (0 === count($clientCertificateList)): ?>
        <p class="plain">
            <?=$this->t('This user does not have any configurations.'); ?>
        </p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th></tr>
            </thead>
            <tbody>
            <?php foreach ($clientCertificateList as $clientCertificate): ?>
                <tr>
                    <td><span title="<?=$this->e($clientCertificate['display_name']); ?>"><?=$this->etr($clientCertificate['display_name'], 25); ?></span></td>
                    <td><?=$this->d($clientCertificate['valid_to']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2><?=$this->t('Connections'); ?></h2>
    <p>
        <?=$this->t('The most recent, concluded, VPN connections with this account.'); ?>
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
        <?=$this->t('The most recent events related to this account.'); ?>
    </p>
    <?php if (empty($userMessages)): ?>
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
