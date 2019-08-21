<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Error'); ?></h2>
    <h3><?=$this->e($code); ?></h3>

    <p><?=$this->t('An error occurred.'); ?></p>

    <p class="error">
        <code><?=$this->e($message); ?></code>
    </p>
<?php $this->stop('content'); ?>
