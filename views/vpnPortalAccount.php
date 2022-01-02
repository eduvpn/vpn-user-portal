<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var bool $showPermissions */?>
<?php /** @var array<\Vpn\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var \Vpn\Portal\Http\UserInfo $userInfo */?>
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
            <th><?=$this->t('Permissions'); ?></th>
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
            <th><?=$this->t('Available Profiles'); ?></th>
            <td>
<?php if (0 === count($profileConfigList)): ?>
                <em><?=$this->t('Your account does not have access to any VPN profile.'); ?></em>
<?php else: ?>
                <ul>
                    <?php foreach ($profileConfigList as $profileConfig): ?>
                        <li><?=$this->e($profileConfig->displayName()); ?></li>
                    <?php endforeach; ?>
                </ul>
<?php endif; ?>
            </td>
        </tr>
    </table>

    <h2><?=$this->t('Authorized Applications'); ?></h2>
    <p>
        <?=$this->t('The list of applications you authorized to create a VPN connection.'); ?>
    </p>
<?php if (0 === count($authorizationList)): ?>
    <p class="plain">
        <?=$this->t('You currently have no authorized applications.'); ?>
    </p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr><th><?=$this->t('Name'); ?></th><th><?=$this->t('Authorized On'); ?></th><th><?=$this->t('Expires On'); ?></th><th></th></tr>
        </thead>
        <tbody>
<?php foreach ($authorizationList as $authorizationInfo): ?>
            <tr>
                <td><span title="<?=$this->e($authorizationInfo->clientId()); ?>"><?=$this->clientIdToDisplayName($authorizationInfo->clientId()); ?></span></td>
                <td><span title="<?=$this->d($authorizationInfo->authorizedAt()); ?>"><?=$this->d($authorizationInfo->authorizedAt(), 'Y-m-d'); ?></span></td>
                <td><span title="<?=$this->d($authorizationInfo->expiresAt()); ?>"><?=$this->d($authorizationInfo->expiresAt(), 'Y-m-d'); ?></span></td>
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
<?php $this->stop('content'); ?>
