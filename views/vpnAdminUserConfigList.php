<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var array<array{profile_id:string,ip_four:string,ip_six:string,disconnected_at:?\DateTimeImmutable,connected_at:\DateTimeImmutable,bytes_transferred:int}> $userConnectionLogEntries */ ?>
<?php /** @var string $userId */?>
<?php /** @var bool $isDisabled */?>
<?php /** @var bool $isSelf */?>
<?php /** @var array<string,string> $idNameMapping */?>
<?php /** @var array<array{log_level:int,log_message:string,date_time:\DateTimeImmutable}> $userMessages */?>
<?php /** @var array<array{profile_id:string,common_name:string,display_name:string,expires_at:\DateTimeImmutable,auth_key:?string}> $clientCertificateList */?>
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
    <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>user">
        <fieldset>
            <input type="hidden" name="user_id" value="<?=$this->e($userId); ?>">
<?php if ($isDisabled): ?>
            <button name="user_action" value="enableAccount"><?=$this->t('Enable Account'); ?></button>
<?php else: ?>
            <button class="warning" name="user_action" value="disableAccount"><?=$this->t('Disable Account'); ?></button>
<?php endif; ?>
            <details>
                <summary><?=$this->t('Danger Zone'); ?></summary>
<?php if ('DbAuthModule' === $authModule): ?>
                <button class="error" name="user_action" value="deleteAccount"><?=$this->t('Delete Account'); ?></button>
<?php else: ?>
                <button class="error" name="user_action" value="deleteAccount"><?=$this->t('Delete Account Data'); ?></button>
                <p class="warning">
                    <?=$this->t('This server uses an external authentication source. Deleting the account data will not prevent the user from authenticating (again)!'); ?></small>
                </p>
<?php endif; ?>
            </details>
        </fieldset>
    </form>
<?php endif; ?>

    <h2><?=$this->t('Certificates'); ?></h2>

    <?php if (0 === count($clientCertificateList)): ?>
        <p class="plain">
            <?=$this->t('This user does not have any active certificates.'); ?>
        </p>
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires'); ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($clientCertificateList as $clientCertificate): ?>
                <tr>
                    <td><span title="<?=$this->e($clientCertificate['display_name']); ?>"><?=$this->etr($clientCertificate['display_name'], 25); ?></span></td>
                    <td><?=$this->d($clientCertificate['expires_at']); ?></td>
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
                <th><?=$this->t('Connected'); ?></th>
                <th><?=$this->t('Disconnected'); ?></th>
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
                <td title="IPv4: <?=$this->e($logEntry['ip_four']); ?>, IPv6: <?=$this->e($logEntry['ip_six']); ?>"><?=$this->d($logEntry['connected_at']); ?></td>
                <td title="<?=$this->bth($logEntry['bytes_transferred']); ?>"><?=$this->d($logEntry['disconnected_at']); ?></td>
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
