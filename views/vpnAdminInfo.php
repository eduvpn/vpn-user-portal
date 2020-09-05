<?php $this->layout('base', ['activeItem' => 'info', 'pageTitle' => $this->t('Info')]); ?>
<?php $this->start('content'); ?>
    <h2>CA</h2>
    <table class="tbl">
        <tbody>
            <tr><th><?=$this->t('Created'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_from']); ?></td></tr>
            <tr><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_to']); ?></td></tr>
<?php if ('RSA' !== $caInfo['ca_key_algo']): ?>
            <tr><th><?=$this->t('Key Algorithm'); ?></th><td><?=$this->e($caInfo['ca_key_algo']); ?></td></tr>
<?php endif; ?>
        </tbody>
    </table>
    <h2><?=$this->t('Profiles'); ?></h2>
    <ul class="profileList">
    <?php foreach ($profileList as $profileId => $profile): ?>
        <li>
        <details>
            <summary><?=$this->e($profile['displayName']); ?></summary>
            <table class="tbl">
                <tbody>
                    <?php foreach ($profile as $k => $v): ?>
                        <tr>
                            <th><?=$this->e($k); ?></th>
                            <?php if (is_array($v) && 0 !== count($v)): ?>
                                <td>
                                <ul>
                                    <?php foreach ($v as $vv): ?>
                                        <li><?=$this->e($vv); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                </td>
                            <?php elseif (true === $v): ?>
                                <td><span class="plain"><?=$this->t('Yes'); ?></span></td>
                            <?php elseif (false === $v): ?>
                                <td><span class="plain"><?=$this->t('No'); ?></span></td>
                            <?php elseif (empty($v)): ?>
                                <td><em><?=$this->t('N/A'); ?></em></td>
                            <?php else: ?>
                                <td><?=$this->e($v); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details></li>
    <?php endforeach; ?>
    </ul>
<?php $this->stop('content'); ?>
