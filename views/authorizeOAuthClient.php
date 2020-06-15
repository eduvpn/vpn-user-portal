<?php $this->layout('base', ['pageTitle' => $this->t('Approve Application')]); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Approve Application'); ?></h1>

    <div class="appAuth">
    <p>
<?=$this->t('An application attempts to establish a VPN connection.'); ?>
    </p>

	<p class="warning">
<?=$this->t('Only approve this when you are trying to establish a VPN connection with this application!'); ?>
	</p>

    <div class="appApproval">
<?php if (null === $display_name): ?>
        <span class="<?=$this->e($client_id); ?>"><?=$this->e($client_id); ?></span>
<?php else: ?>
        <span class="<?=$this->e($client_id); ?>"><?=$this->e($display_name); ?></span>
<?php endif; ?>
        <form class="frm" method="post">
            <fieldset>
                <button type="submit" name="approve" value="yes"><?=$this->t('Approve'); ?></button>
            </fieldset>
        </form>
    </div>

    <details>
        <summary>
<?=$this->t('Why is this necessary?'); ?>
        </summary>
        <p>
<?=$this->t('To prevent malicious applications from secretly establishing a VPN connection on your behalf, you have to explicitly approve this application first.'); ?>
        </p>
    </details>
    </div>
<?php $this->stop('content'); ?>
