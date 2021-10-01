<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */ ?>
<?php /** @var \LC\Portal\WireGuard\WgClientConfig $wgClientConfig */ ?>
<?php $this->layout('base', ['activeItem' => 'configurations', 'pageTitle' => $this->t('Configurations')]); ?>
<?php $this->start('content'); ?>
    <h3><?= $this->t('WireGuard Configuration'); ?></h3>
    <p>
<?= $this->t('On your mobile device, you can scan the QR code with the WireGuard application. On your desktop or laptop computer you can paste the file in the WireGuard application.'); ?>
    </p>
    <h3><?= $this->t('QR'); ?></h3>
    <p>
        <img src="data:image/png;base64,<?= $this->e($wgClientConfig->getQr()); ?>">
    </p>
    <h3><?= $this->t('File'); ?></h3>
    <blockquote>
        <pre><?= $this->e($wgClientConfig->get()); ?></pre>
    </blockquote>
    </details>
<?php $this->stop('content'); ?>
