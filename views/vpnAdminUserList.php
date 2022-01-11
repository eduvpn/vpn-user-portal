<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<array{user_id:string,last_seen:\DateTimeImmutable,permission_list:array<string>,is_disabled:bool}> $userList */?>
<?php /** @var string $requestRoot */?>
<?php /** @var ?string $listUsers */?>
<?php $this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
<?php if (null === $listUsers || 'all' === $listUsers): ?>
    <p class="filter"><?=$this->t('All'); ?> │ <a href="?list_users=active"><?=$this->t('Active'); ?></a> │ <a href="?list_users=disabled"><?=$this->t('Disabled'); ?></a></p>
<?php endif; ?>
<?php if ('active' === $listUsers) : ?>
    <p class="filter"><a href="?list_users=all"><?=$this->t('All'); ?></a> │ <?=$this->t('Active'); ?> │ <a href="?list_users=disabled"><?=$this->t('Disabled'); ?></a></p>
<?php endif; ?>
<?php if ('disabled' === $listUsers) : ?>
    <p class="filter    "><a href="?list_users=all"><?=$this->t('All'); ?></a> │ <a href="?list_users=active"><?=$this->t('Active'); ?></a> │ <?=$this->t('Disabled'); ?></p>
<?php endif; ?>
<table class="tbl">
    <thead>
        <tr><th><?=$this->t('User ID'); ?></th></th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($userList as $user): ?>
            <tr>
                <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($user['user_id'], 'rawurlencode'); ?>" title="<?=$this->e($user['user_id']); ?>"><?=$this->etr($user['user_id'], 50); ?></a></td>
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
<?php $this->stop('content'); ?>
