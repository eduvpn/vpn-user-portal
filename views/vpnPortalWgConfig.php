<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */ ?>
<?php /** @var \Vpn\Portal\WireGuard\ClientConfig $wireGuardClientConfig */ ?>
<?php $this->layout('base', ['activeItem' => 'configurations', 'pageTitle' => $this->t('Configurations')]); ?>
<?php $this->start('content'); ?>
    <h2><?= $this->t('WireGuard Configuration'); ?></h2>
    <p>
<?= $this->t('Import or copy/paste this configuration to your WireGuard application.'); ?>
    </p>
    <blockquote>
        <pre><?= $this->e($wireGuardClientConfig->get()); ?></pre>
    </blockquote>
    </details>
<?php $this->stop('content'); ?>
