<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var \LC\Portal\Http\UserInfo $userInfo */?>
<?php /** @var array<array{log_level:int,log_message:string,date_time:\DateTimeImmutable}> $userMessages */?>
<?php /** @var array<\fkooman\OAuth\Server\Authorization> $authorizationList */?>
<?php /** @var string $authModule */?>
<?php $this->layout('base', ['activeItem' => 'account', 'pageTitle' => $this->t('Account')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Info'); ?></h2>
    <table class="tbl">
        <tr>
            <th><?=$this->t('User ID'); ?></th>
            <td><code><?=$this->e($userInfo->userId()); ?></code></td>
        </tr>
        <?php if ('DbAuthModule' === $authModule) :?>
            <tr>
                <th></th>
                <td><form class="frm" method="get" action="passwd"><button><?=$this->t('Change Password'); ?></button></form></td>
            </tr>
        <?php endif; ?>

        <?php if ($showPermissions && 0 !== count($userInfo->permissionList())): ?>
        <tr>
            <th><?=$this->t('Permission(s)'); ?></th>
            <td>
                <ul>
                    <?php foreach ($userInfo->permissionList() as $userPermission): ?>
                        <li><code><?=$this->e($userPermission); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
        <?php endif; ?>

        <tr>
            <th><?=$this->t('Profile(s)'); ?></th>
            <td>
                <ul>
                    <?php foreach ($profileConfigList as $profileConfig): ?>
                        <li><?=$this->e($profileConfig->displayName()); ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
    </table>

    <h2><?=$this->t('Authorized Applications'); ?></h2>
    <p>
        <?=$this->t('The list of applications you authorized to create a VPN connection.'); ?>
    </p>
<?php if (0 === count($authorizationList)): ?>
    <p class="plain">
        <?=$this->t('No authorized applications yet.'); ?>
    </p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr><th><?=$this->t('Name'); ?></th><th></th></tr>
        </thead>
        <tbody>
<?php foreach ($authorizationList as $authorizationInfo): ?>
            <tr>
                <td><span title="<?=$this->e($authorizationInfo->clientId()); ?>"><?=$this->clientIdToDisplayName($authorizationInfo->clientId()); ?></span></td>
                <td class="text-right">
                    <form class="frm" method="post" action="removeClientAuthorization">
                        <input type="hidden" name="auth_key" value="<?=$this->e($authorizationInfo->authKey()); ?>">
                        <button class="warning"><?=$this->t('Revoke'); ?></button>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

    <h2><?=$this->t('Events'); ?></h2>
    <p>
        <?=$this->t('The most recent events related to this account.'); ?>
    </p>
<?php if (0 === count($userMessages)): ?>
        <p class="plain"><?=$this->t('No events yet.'); ?></p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr><th><?=$this->t('Date/Time'); ?></th><th><?=$this->t('Message'); ?></th></tr>
        </thead>
        <tbody>
<?php foreach ($userMessages as $message): ?>
            <tr>
                <td><?=$this->d($message['date_time']); ?></td>
                <td><?=$this->e($message['log_message']); ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php $this->stop('content'); ?>
