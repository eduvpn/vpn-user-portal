<?php declare(strict_types=1);
$this->layout('base', ['activeItem' => 'certificates']); ?>
<?php $this->start('content'); ?>
    <?php if (0 === count($userCertificateList)): ?>
        <p class="plain">
            <?=$this->t('There are currently no issued certificates. <a href="new">Download</a> a new configuration.'); ?>
        </p>                    
    <?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Name'); ?></th><th><?=$this->t('Issued'); ?> (<?=$this->e(date('T')); ?>)</th><th><?=$this->t('Expires'); ?> (<?=$this->e(date('T')); ?>)</th><th></th></tr> 
            </thead>
            <tbody>
            <?php foreach ($userCertificateList as $userCertificate): ?>
                <tr>
                    <td><?=$this->e($userCertificate['display_name']); ?></td>
                    <td><?=$this->d($userCertificate['valid_from']); ?></td>
                    <td><?=$this->d($userCertificate['valid_to']); ?></td>
                    <td class="text-right">
                        <form method="post" action="deleteCertificate">
                            <input type="hidden" name="commonName" value="<?=$this->e($userCertificate['common_name']); ?>">
                            <button type="submit"><?=$this->t('Delete'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop(); ?>
