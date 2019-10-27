<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Error'); ?></h1>
    <h2><?=$this->e($code); ?></h2>

    <p><?=$this->t('An error occurred.'); ?></p>

    <p class="error">
        <code><?=$this->e($message); ?></code>
    </p>
<?php $this->stop('content'); ?>
