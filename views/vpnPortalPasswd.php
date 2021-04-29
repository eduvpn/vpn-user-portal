<?php declare(strict_types=1); ?>
<?php $this->layout('base', ['activeItem' => 'account', 'pageTitle' => $this->t('Account')]); ?>
<?php $this->start('content'); ?>
    <p>
        <?=$this->t('Here you can change the password for your account.'); ?>
    </p>

    <?php if (isset($errorCode)): ?>
        <p class="error">
        <?php if ('wrongPassword' === $errorCode): ?>
            <?=$this->t('Current Password not correct!'); ?>
        <?php elseif ('noMatchingPassword' === $errorCode): ?>
            <?=$this->t('New Password and New Password (confirm) MUST match!'); ?>
        <?php else: ?>
            <?=$this->e($errorCode); ?>
        <?php endif; ?>
        </p>
    <?php endif; ?>

    <form class="frm" method="post">
        <fieldset>
            <label for="userName"><?=$this->t('Username'); ?></label>
            <input size="30" type="text"     id="userName" name="userName" value="<?=$this->e($userId); ?>" autocapitalize="off" disabled="disabled" required>

            <label for="userPass"><?=$this->t('Current Password'); ?></label>
            <input size="30" type="password" id="userPass" name="userPass" placeholder="<?=$this->t('Current Password'); ?>" autofocus required>

            <label for="newUserPass"><?=$this->t('New Password'); ?></label>
            <input size="30" type="password" id="newUserPass" name="newUserPass" placeholder="<?=$this->t('New Password'); ?>" required>

            <label for="newUserPassConfirm"><?=$this->t('New Password (confirm)'); ?></label>
            <input size="30" type="password" id="newUserPassConfirm" name="newUserPassConfirm" placeholder="<?=$this->t('New Password (confirm)'); ?>" required>
        </fieldset>
        <fieldset>
            <button type="submit"><?=$this->t('Confirm'); ?></button>
        </fieldset>
    </form>
<?php $this->stop('content'); ?>
