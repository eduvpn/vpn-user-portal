<?php $this->layout('base', ['activeItem' => 'messages', 'pageTitle' => $this->t('Messages')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Message of the Day'); ?></h2>
    <p>
        <?=$this->t('This message will be shown on the "Home" screen.'); ?>
    </p>

    <form class="frm" method="post">
        <fieldset>
            <label for="msg"><?=$this->t('Message'); ?></label>
            <textarea id="msg" name="message_body" rows="8"><?php if ($motdMessage): ?><?=$this->e($motdMessage['message']); ?><?php endif; ?></textarea>
        </fieldset>
        <fieldset>
            <button name="message_action" value="set" type="submit"><?=$this->t('Set'); ?></button>
            <?php if ($motdMessage): ?>
                <input type="hidden" name="message_id" value="<?=$this->e($motdMessage['id']); ?>">
                <button class="warning" name="message_action" value="delete"><?=$this->t('Delete'); ?></button>
            <?php endif; ?>
        </fieldset>
    </form>
<?php $this->stop('content'); ?>
