<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var bool $_form_auth_invalid_credentials */?>
<?php /** @var string $requestRoot */?>
<?php /** @var string $_form_auth_invalid_credentials_user */?>
<?php /** @var string $_form_auth_redirect_to */?>
<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please sign in with your username and password.'); ?>
        </p>

<?php if ($_form_auth_invalid_credentials): ?>
        <p class="error">
            <?=$this->t('The credentials you provided were not correct.'); ?>
        </p>
<?php endif; ?>

        <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>_form/auth/verify">
            <fieldset>
<?php if ($_form_auth_invalid_credentials): ?>
                <label for="userName"><?=$this->t('Username'); ?></label>
                <input type="text" id="userName" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" value="<?=$this->e($_form_auth_invalid_credentials_user); ?>" required>
                <label for="userPass"><?=$this->t('Password'); ?></label>
                <input type="password" id="userPass" name="userPass" placeholder="<?=$this->t('Password'); ?>" autofocus required>
<?php else: ?>
                <label for="userName"><?=$this->t('Username'); ?></label>
                <input type="text" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
                <label for="userPass"><?=$this->t('Password'); ?></label>
                <input type="password" name="userPass" placeholder="<?=$this->t('Password'); ?>" required>
<?php endif; ?>
            </fieldset>
            <fieldset>
                <input type="hidden" name="_form_auth_redirect_to" value="<?=$this->e($_form_auth_redirect_to); ?>">
                <button type="submit"><?=$this->t('Sign In'); ?></button>
            </fieldset>
        </form>
    </div>
<?php $this->stop('content'); ?>
