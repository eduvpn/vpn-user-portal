<?php $this->layout('base', ['activeItem' => 'home']); ?>
<?php $this->start('content'); ?>
<h1><?=$this->t('Home'); ?></h1>
<?php if ($motdMessage): ?>
    <p class="plain"><?=$this->batch($motdMessage['message'], 'escape|nl2br'); ?></p>
<?php endif; ?>
<p>
    <?=$this->t('Welcome to this VPN service!'); ?>
</p>
<?php $this->stop('content'); ?>
