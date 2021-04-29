<?php declare(strict_types=1); ?>
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
            <label for="dateTime"><?=$this->t('Date/Time'); ?> (<?=$this->e(date('T')); ?>)</label>
            <input id="dateTime" name="date_time" type="text" size="30" value="<?php if ($date_time): ?><?=$this->d($date_time); ?><?php else: ?><?=$this->d($now); ?><?php endif; ?>" required>
            <label for="ipAddress"><?=$this->t('IP Address'); ?></label>
            <input id="ipAddress" name="ip_address" type="text" size="30" value="<?php if ($ip_address): ?><?=$this->e($ip_address); ?><?php endif; ?>" placeholder="fdc6:6794:d2bf:1::1000" required>
        </fieldset>
        <fieldset>
            <button type="submit"><?=$this->t('Search'); ?></button>
        </fieldset>
    </form>

    <?php if (isset($result)): ?>
        <h2><?=$this->t('Results'); ?></h2>
        <?php if (false === $result): ?>
            <p class="plain">
                <?=$this->t('There are no results matching your criteria.'); ?>
            </p>
        <?php else: ?>
            <table class="tbl">
                <tbody>
                    <tr>
                        <th><?=$this->t('Profile'); ?></th>
                        <td><?=$this->e($result['profile_id']); ?></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('User ID'); ?></th>
                        <td><a href="<?=$this->e($requestRoot); ?>user?user_id=<?=$this->e($result['user_id'], 'rawurlencode'); ?>"><?=$this->e($result['user_id']); ?></a></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Name'); ?></th>
                        <td><?=$this->e($result['common_name']); ?></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('IPs'); ?></th>
                        <td><ul><li><?=$this->e($result['ip_four']); ?></li><li><?=$this->e($result['ip_six']); ?></li></ul></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Connected'); ?> (<?=$this->e(date('T')); ?>)</th>
                        <td><?=$this->d($result['connected_at']); ?></td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Disconnected'); ?> (<?=$this->e(date('T')); ?>)</th>
                        <td>
                            <?php if ($result['disconnected_at']): ?>
                                <?=$this->d($result['disconnected_at']); ?>
                            <?php else: ?>
                                <em><?=$this->t('N/A'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?=$this->t('Client Lost'); ?></th>
                        <td>
                            <?php if ($result['client_lost']): ?>
                                <span class="plain"><?=$this->t('Yes'); ?></span>
                            <?php else: ?>
                                <span class="plain"><?=$this->t('No'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
<?php $this->stop('content'); ?>
