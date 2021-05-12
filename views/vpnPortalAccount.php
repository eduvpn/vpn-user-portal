<?php declare(strict_types=1); ?>
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

        <?php if (0 !== count($userPermissions)): ?>
        <tr>
            <th><?=$this->t('Permission(s)'); ?></th>
            <td>
                <ul>
                    <?php foreach ($userPermissions as $userPermission): ?>
                        <li><code><?=$this->e($userPermission); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <details>
        <p>
            <?=$this->t('The list of applications you authorized to create a VPN connection.'); ?>
        </p>
        <summary><?=$this->t('Authorized Applications'); ?></summary>
<?php if (0 === count($authorizedClientInfoList)): ?>
        <p class="plain">
            <?=$this->t('No authorized applications yet.'); ?>
        </p>
<?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Name'); ?></th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($authorizedClientInfoList as $authorizedClientInfo): ?>
                <tr>
                    <td><span title="<?=$this->e($authorizedClientInfo['client_id']); ?>"><?=$this->e($authorizedClientInfo['display_name']); ?></span></td>
                    <td class="text-right">
                        <form class="frm" method="post" action="removeClientAuthorization">
                            <input type="hidden" name="client_id" value="<?=$this->e($authorizedClientInfo['client_id']); ?>">
                            <input type="hidden" name="auth_key" value="<?=$this->e($authorizedClientInfo['auth_key']); ?>">
                            <button class="warning"><?=$this->t('Revoke'); ?></button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </details>

    <details>
        <summary><?=$this->t('Connections'); ?></summary>
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
                    <td title="<?=$this->bth((int) $logEntry['bytes_transferred']); ?>"><?=$this->d($logEntry['disconnected_at']); ?></td>
                </tr>
<?php endif; ?>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </details>

    <details>
        <summary><?=$this->t('Events'); ?></summary>
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
    </details>
<?php $this->stop('content'); ?>
