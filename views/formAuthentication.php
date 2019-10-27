<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <h1><?=$this->t('Sign In'); ?></h1>
    <div class="auth">
        <p>
            <?=$this->t('Please sign in with your username and password.'); ?>
        </p>

<?php if ($_form_auth_invalid_credentials): ?>
        <p class="error">
            <?=$this->t('The credentials you provided were not correct.'); ?>
        </p>
<?php endif; ?>

        <form method="post" action="<?=$this->e($requestRoot); ?>_form/auth/verify">
            <fieldset>
<?php if ($_form_auth_invalid_credentials): ?>
                <input type="text" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" value="<?=$this->e($_form_auth_invalid_credentials_user); ?>" required>
                <input type="password" name="userPass" placeholder="<?=$this->t('Password'); ?>" autofocus required>
<?php else: ?>
                <input type="text" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
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
