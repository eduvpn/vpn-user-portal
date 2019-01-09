<?php $this->layout('base', ['activeItem' => 'users']); ?>
<?php $this->start('content'); ?>
    <?php if (empty($userList)): ?>
        <p class="plain">
            <?=$this->t('There are no users with VPN configurations.'); ?>
        </p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th><?=$this->t('User ID'); ?></th><th class="text-right"><?=$this->t('Status'); ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($userList as $user): ?>
                    <tr>
                        <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($user['user_id'], 'rawurlencode'); ?>"><?=$this->e($user['user_id']); ?></a></td>
                        <td class="text-right">
                            <?php if ($user['has_totp_secret']): ?>
                                <span class="plain"><?=$this->t('TOTP'); ?></span>
                            <?php endif; ?>
                            <?php if ($user['is_disabled']): ?>
                                <span class="error"><?=$this->t('Disabled'); ?></span>
                            <?php else: ?>
                                <span class="success"><?=$this->t('Active'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop(); ?>
