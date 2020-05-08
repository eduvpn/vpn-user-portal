<?php $this->layout('base', ['activeItem' => 'info']); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Info'); ?></h1>
    <h2>CA</h2>
    <table class="tbl">
        <tbody>
            <tr><th><?=$this->t('Created'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_from']); ?></td></tr>
            <tr><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th><td><?=$this->d($caInfo['valid_to']); ?></td></tr>
        </tbody>
    </table>
    <h2><?=$this->t('Profiles'); ?></h2>
    <ul>
    <?php foreach ($profileList as $profileId => $profile): ?>
        <li><a href="#profile_<?=$this->e($profileId); ?>"><?=$this->e($profile['displayName']); ?></a></li>
    <?php endforeach; ?>
    </ul>
    <?php foreach ($profileList as $profileId => $profile): ?>
        <h3 id="profile_<?=$this->e($profileId); ?>"><?=$this->e($profile['displayName']); ?></h3>
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
    <?php endforeach; ?>
<?php $this->stop('content'); ?>
