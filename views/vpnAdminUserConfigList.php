<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<array{profile_id:string,ip_four:string,ip_six:string,connected_at:\DateTimeImmutable,disconnected_at:?\DateTimeImmutable}> $userConnectionLogEntries */ ?>
<?php /** @var string $userId */?>
<?php /** @var bool $isDisabled */?>
<?php /** @var bool $isSelf */?>
<?php /** @var array<\Vpn\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<array{log_level:int,log_message:string,date_time:\DateTimeImmutable}> $userMessages */?>
<?php /** @var array<array{profile_id:string,common_name?:string,public_key?:string,display_name:string,expires_at:\DateTimeImmutable,auth_key:?string}> $configList */ ?>
<?php /** @var string $requestRoot */?>
<?php /** @var string $authModule */?>
<?php $this->layout('base', ['activeItem' => 'users', 'pageTitle' => $this->t('Users')]); ?>
<?php $this->start('content'); ?>
    <p>
        <?=$this->t('Managing user <code>%userId%</code>.'); ?>
    </p>

<?php if ($isSelf): ?>
        <p class="warning"><?=$this->t('You cannot manage your own user account.'); ?></p>
<?php else: ?>
<?php if ($isDisabled): ?>
    <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>user_enable_account">
        <fieldset>
            <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
            <button><?=$this->t('Enable Account'); ?></button>
        </fieldset>
    </form>
<?php else: ?>
    <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>user_disable_account">
        <fieldset>
            <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
            <button class="warning"><?=$this->t('Disable Account'); ?></button>
        </fieldset>
    </form>
<?php endif; ?>
    <details>
        <summary><?=$this->t('Danger Zone'); ?></summary>
        <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>user_delete_account">
            <fieldset>
                <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
<?php if ('DbAuthModule' === $authModule): ?>
                <button class="error"><?=$this->t('Delete Account'); ?></button>
<?php else: ?>
                <button class="error"><?=$this->t('Delete Account Data'); ?></button>
                <p class="warning">
                    <?=$this->t('This server uses an external authentication source. Deleting the account data will not prevent the user from authenticating (again)!'); ?></small>
                </p>
<?php endif; ?>
            </fieldset>
        </form>
    </details>
<?php endif; ?>

    <h2><?=$this->t('Configurations'); ?></h2>

    <?php if (0 === count($configList)): ?>
        <p class="plain">
            <?=$this->t('This user does not have any active configurations.'); ?>
        </p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Profile'); ?></th><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires'); ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($configList as $configEntry): ?>
                <tr>
                    <td>
                        <span title="<?=$this->e($configEntry['profile_id']); ?>"><?=$this->profileIdToDisplayName($profileConfigList, $configEntry['profile_id']); ?></span>
                    </td>
<?php if (array_key_exists('common_name', $configEntry)): ?>
                    <td><span title="<?=$this->e($configEntry['common_name']); ?>"><?=$this->etr($configEntry['display_name'], 25); ?></span></td>
<?php endif; ?>
<?php if (array_key_exists('public_key', $configEntry)): ?>
                    <td><span title="<?=$this->e($configEntry['public_key']); ?>"><?=$this->etr($configEntry['display_name'], 25); ?></span></td>
<?php endif; ?>
                    <td><?=$this->d($configEntry['expires_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2><?=$this->t('Connections'); ?></h2>
    <p>
        <?=$this->t('The most recent VPN connections with this account.'); ?>
    </p>
<?php if (0 === count($userConnectionLogEntries)): ?>
    <p class="plain"><?=$this->t('No connections yet.'); ?></p>
<?php else: ?>
    <table class="tbl">
        <thead>
            <tr>
                <th><?=$this->t('Profile'); ?></th>
                <th><?=$this->t('Connected'); ?></th>
                <th><?=$this->t('Disconnected'); ?></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($userConnectionLogEntries as $logEntry): ?>
            <tr>
                <td title="<?=$this->e($logEntry['profile_id']); ?>"><?=$this->profileIdToDisplayName($profileConfigList, $logEntry['profile_id']); ?></td>
                <td title="IPv4: <?=$this->e($logEntry['ip_four']); ?>, IPv6: <?=$this->e($logEntry['ip_six']); ?>"><?=$this->d($logEntry['connected_at']); ?></td>
                <td>
<?php if (null === $logEntry['disconnected_at']): ?>
                    <em><?=$this->t('N/A'); ?></em>
<?php else: ?>
                    <?=$this->d($logEntry['disconnected_at']); ?>
<?php endif; ?>
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
    <?php if (empty($userMessages)): ?>
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
