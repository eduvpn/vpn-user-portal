<?php declare(strict_types=1);
$this->layout('base'); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Error'); ?></h2>
    <h3><?=$this->e($e->getCode()); ?></h3>
    <p><?=$this->t('An error occurred.'); ?></p>
    <p class="error">
        <?=$this->e($e->getMessage()); ?>
    </p>
    <details class="error">
        <pre><?=$this->e($e); ?></pre>
<?php while (null !== $previousException = $e->getPrevious()): ?>
        <pre><?=$this->e($previousException); ?></pre>
<?php endwhile; ?>
    </details>
<?php $this->stop(); ?>
