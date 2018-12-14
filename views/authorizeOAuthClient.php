<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <ul class="menu">
        <li class="active"><span><?=$this->t('Approval'); ?></span></li>
    </ul>
    
    <?php if (null === $display_name): ?>
        <?php $display_name = $client_id; ?>
    <?php endif; ?>

    <p>
        <?=$this->t('<strong title="%client_id%">%display_name%</strong> wants to manage your VPN configurations.'); ?>
    </p>

    <form method="post">
        <button class="error" type="submit" name="approve" value="no"><?=$this->t('Reject'); ?></button>
        <button class="success" type="submit" name="approve" value="yes"><?=$this->t('Approve'); ?></button>
    </form>
<?php $this->stop(); ?>
