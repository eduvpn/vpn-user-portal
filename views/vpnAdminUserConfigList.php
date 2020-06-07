<?php $this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Users'); ?></h1>
    <p>
        <?=$this->t('Managing user <code>%userId%</code>.'); ?>
    </p>

    <?php if ($isSelf): ?>
        <p class="warning"><?=$this->t('You cannot manage your own user account.'); ?></p>
    <?php endif; ?>

    <form method="post" action="<?=$this->e($requestRoot); ?>user">
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

    <h2><?=$this->t('Events'); ?></h2>
    <p>
        <?=$this->t('This is a list of events that occurred related to this user account.'); ?>
    </p>
    <?php if (empty($userMessages)): ?>
        <p class="plain"><?=$this->t('No events yet.'); ?></p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Date/Time'); ?> (<?=$this->e(date('T')); ?>)</th><th><?=$this->t('Message'); ?></th><th><?=$this->t('Type'); ?></tr>
            </thead>
            <tbody>
                <?php foreach ($userMessages as $message): ?>
                    <tr>
                        <td><?=$this->d($message['date_time']); ?></td>
                        <td><?=$this->e($message['message']); ?></td>
                        <td><span class="plain"><?=$this->e($message['type']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop('content'); ?>
