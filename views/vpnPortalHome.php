<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */ ?>
<?php $this->layout('base', ['activeItem' => 'home', 'pageTitle' => $this->t('Home')]); ?>
<?php $this->start('content'); ?>
<p class="lead"><?= $this->t('Welcome to this VPN service!'); ?></p>
<?php $this->stop('content'); ?>
