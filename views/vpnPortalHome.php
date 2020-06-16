<?php $this->layout('base', ['activeItem' => 'home', 'pageTitle' => $this->t('Home')]); ?>
<?php $this->start('content'); ?>
<p class="lead"><?=$this->t('Welcome to this VPN service!'); ?></p>
<?php if ($motdMessage): ?>
    <blockquote><?=$this->batch($motdMessage['message'], 'escape|nl2br'); ?></blockquote>
<?php endif; ?>
<?php $this->stop('content'); ?>
