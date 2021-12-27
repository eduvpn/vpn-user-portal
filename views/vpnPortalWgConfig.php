<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */ ?>
<?php /** @var \Vpn\Portal\WireGuard\ClientConfig $wireGuardClientConfig */ ?>
<?php $this->layout('base', ['activeItem' => 'home', 'pageTitle' => $this->t('Home')]); ?>
<?php $this->start('content'); ?>
    <h2><?= $this->t('WireGuard Configuration'); ?></h2>
<?php if (null !== $qrCode = $wireGuardClientConfig->getQr()): ?>
    <p>
<?=$this->t('Scan this QR code with your mobile device.'); ?>
    </p>
<?=$qrCode; ?>
<?php endif; ?>
    <p>
<?=$this->t('Import or copy/paste this configuration to your WireGuard application.'); ?>
    </p>
    <blockquote>
        <pre><?= $this->e($wireGuardClientConfig->get()); ?></pre>
    </blockquote>
    </details>
<?php $this->stop('content'); ?>
