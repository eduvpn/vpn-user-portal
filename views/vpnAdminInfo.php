<?php $this->layout('base', ['activeItem' => 'info']); ?>
<?php $this->start('content'); ?>
    <?php foreach ($profileList as $profile): ?>
        <h2><?=$this->e($profile['displayName']); ?></h2>
        <table>
            <tbody>
                <?php foreach ($profile as $k => $v): ?>
                    <tr>
                        <th><?=$this->e($k); ?></th>
                        <?php if (is_array($v) && 0 !== count($v)): ?>
                            <td>
                            <ul class="simple">
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
<?php $this->stop(); ?>
