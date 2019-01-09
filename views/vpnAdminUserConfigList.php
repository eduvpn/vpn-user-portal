<?php $this->layout('base', ['activeItem' => 'users']); ?>
<?php $this->start('content'); ?>
    <p>
        <?=$this->t('Managing user <em>%userId%</em>.'); ?>
    </p>
    
    <form method="post" action="<?=$this->e($requestRoot); ?>user">
        <fieldset>
            <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
            <?php if ($isDisabled): ?>
                <button name="user_action" value="enableUser" class="success"><?=$this->t('Enable User'); ?></button>
            <?php else: ?>
                <button name="user_action" value="disableUser" class="error"><?=$this->t('Disable User'); ?></button>
            <?php endif; ?>
            <?php if ($hasTotpSecret): ?>
                <button name="user_action" value="deleteTotpSecret" class="error"><?=$this->t('Delete TOTP Secret'); ?></button>
            <?php endif; ?>
        </fieldset>
    </form>

    <h2><?=$this->t('Configurations'); ?></h2>

    <?php if (0 === count($clientCertificateList)): ?>
        <p class="plain">
            <?=$this->t('This user does not have any configurations.'); ?>
        </p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th><?=$this->t('Name'); ?></th><th><?=$this->t('Issued'); ?> (<?=$this->e(date('T')); ?>)</th><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th></tr> 
            </thead>
            <tbody>
            <?php foreach ($clientCertificateList as $clientCertificate): ?>
                <tr>
                    <td><?=$this->e($clientCertificate['display_name']); ?></td>
                    <td><?=$this->e($clientCertificate['valid_from']); ?></td>
                    <td><?=$this->e($clientCertificate['valid_to']); ?></td>
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
        <table>
            <thead>
                <tr><th><?=$this->t('Date/Time'); ?> (<?=$this->e(date('T')); ?>)</th><th><?=$this->t('Message'); ?></th><th><?=$this->t('Type'); ?></tr>
            </thead>
            <tbody>
                <?php foreach ($userMessages as $message): ?>
                    <tr>
                        <td><?=$this->e($message['date_time']); ?></td>
                        <td><?=$this->e($message['message']); ?></td>
                        <td><span class="plain"><?=$this->e($message['type']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop(); ?>
