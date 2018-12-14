<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <ul class="menu">
        <li class="active"><span><?=$this->t('Error'); ?></span></li>
    </ul>

    <h2><?=$this->e($code); ?></h2>

    <p>
    <?=$this->t('An error occurred.'); ?>
    </p>

    <p class="error">
        <code>
            <?=$this->e($message); ?>
        </code>
    </p>
<?php $this->stop(); ?>
