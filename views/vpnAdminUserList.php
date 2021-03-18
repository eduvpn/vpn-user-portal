<?php declare(strict_types=1);
$this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
    <?php if (empty($userList)): ?>
        <p class="plain">
            <?=$this->t('No user account(s) to show.'); ?>
        </p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('User ID'); ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($userList as $user): ?>
                    <tr>
                        <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($user['user_id'], 'rawurlencode'); ?>" title="<?=$this->e($user['user_id']); ?>"><?=$this->etr($user['user_id'], 50); ?></a></td>
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
<?php $this->stop('content'); ?>
