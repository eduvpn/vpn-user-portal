<?php declare(strict_types=1); ?>
<?php $this->layout('base', ['activeItem' => 'configurations', 'pageTitle' => $this->t('Configurations')]); ?>
<?php $this->start('content'); ?>
    <h3><?= $this->t('WireGuard Configuration'); ?></h3>
    <p>
<?= $this->t('On your mobile device, you can scan the QR code with the WireGuard application. On your desktop or laptop computer you can paste the file in the WireGuard application.'); ?>
    </p>
    <h3><?= $this->t('QR'); ?></h3>
    <p>
        <img src="qr?qr_text=<?= urlencode($this->e($wgConfig)); ?>">
    </p>
    <h3><?= $this->t('File'); ?></h3>
    <blockquote>
        <pre><?= $this->e($wgConfig); ?></pre>
    </blockquote>
    </details>
<?php $this->stop('content'); ?>
