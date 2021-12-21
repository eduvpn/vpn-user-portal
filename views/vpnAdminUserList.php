<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<array{user_id:string,last_seen:\DateTimeImmutable,permission_list:array<string>,is_disabled:bool}> $userList */?>
<?php /** @var string $requestRoot */?>
<?php $this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
    <?php if (empty($userList)): ?>
        <p class="plain">
            <?=$this->t('No user account(s) to show.'); ?>
        </p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('User ID'); ?></th><th><?=$this->t('Last Seen'); ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($userList as $user): ?>
                    <tr>
                        <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($user['user_id'], 'rawurlencode'); ?>" title="<?=$this->e($user['user_id']); ?>"><?=$this->etr($user['user_id'], 50); ?></a></td>
                        <td><span><?=$this->d($user['last_seen']); ?></td>
                        <td class="text-right">
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
