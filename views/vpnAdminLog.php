<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<\Vpn\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var ?\DateTimeImmutable $date_time */?>
<?php /** @var ?string $ip_address */?>
<?php /** @var \DateTimeImmutable $now */?>
<?php /** @var bool $showResults */?>
<?php /** @var string $requestRoot */?>
<?php /** @var array<array{user_id:string,profile_id:string,ip_four:string,ip_six:string,connected_at:\DateTimeImmutable,disconnected_at:?\DateTimeImmutable}> $logEntries */?>
<?php $this->layout('base', ['activeItem' => 'log', 'pageTitle' => $this->t('Log')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Search'); ?></h2>
    <p>
        <?=$this->t('Find the user identifier that used an IPv4 or IPv6 address at a particular point in time.'); ?>
    </p>
    <p>
        <?=$this->t('The <em>Date/Time</em> field accepts dates of the format <code>Y-m-d H:i:s</code>, e.g. <code>2019-01-01 08:00:00</code>.'); ?>
    </p>

    <form class="frm" method="post">
        <fieldset>
            <label for="dateTime"><?=$this->t('Date/Time'); ?></label>
            <input id="dateTime" name="date_time" type="text" size="30" value="<?php if (null !== $date_time): ?><?=$this->d($date_time, 'Y-m-d H:i:s'); ?><?php else: ?><?=$this->d($now, 'Y-m-d H:i:s'); ?><?php endif; ?>" required>
            <label for="ipAddress"><?=$this->t('IP Address'); ?></label>
            <input id="ipAddress" name="ip_address" type="text" size="30" value="<?php if ($ip_address): ?><?=$this->e($ip_address); ?><?php endif; ?>" placeholder="fdc6:6794:d2bf:1::1000" required>
        </fieldset>
        <fieldset>
            <button type="submit"><?=$this->t('Search'); ?></button>
        </fieldset>
    </form>

<?php if ($showResults): ?>
    <h2><?=$this->t('Results'); ?></h2>
<?php if (0 === count($logEntries)) :?>
        <p class="plain">
<?=$this->t('There are no results matching your criteria.'); ?>
        </p>
<?php else: ?>
<?php foreach ($logEntries as $logEntry): ?>
            <table class="tbl">
                <tbody>
                    <tr>
                        <th><?=$this->t('Profile'); ?></th>
                        <td><?=$this->profileIdToDisplayName($profileConfigList, $logEntry['profile_id']); ?></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('User ID'); ?></th>
                        <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($logEntry['user_id'], 'rawurlencode'); ?>"><?=$this->e($logEntry['user_id']); ?></a></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('IPs'); ?></th>
                        <td><ul><li><?=$this->e($logEntry['ip_four']); ?></li><li><?=$this->e($logEntry['ip_six']); ?></li></ul></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Connected'); ?></th>
                        <td><?=$this->d($logEntry['connected_at']); ?></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Disconnected'); ?></th>
                        <td>
                            <?php if (null !== $logEntry['disconnected_at']): ?>
                                <?=$this->d($logEntry['disconnected_at']); ?>
                            <?php else: ?>
                                <em><?=$this->t('N/A'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<?php $this->stop('content'); ?>
