<?php $this->layout('base'); ?>
<?php $this->start('content'); ?>
    <ul class="menu">
        <li class="active"><span><?=$this->t('Sign In'); ?></span></li>
    </ul>

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
                <label for="userName"><?=$this->t('Username'); ?></label>
                <input size="30" type="text"     id="userName" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" value="<?=$this->e($_form_auth_invalid_credentials_user); ?>" required>
                <label for="userPass"><?=$this->t('Password'); ?></label>
                <input size="30" type="password" id="userPass"name="userPass" placeholder="<?=$this->t('Password'); ?>" autofocus required>
            <?php else: ?>
                <label for="userName"><?=$this->t('Username'); ?></label>
                <input size="30" type="text"     id="userName" name="userName" autocapitalize="off" placeholder="<?=$this->t('Username'); ?>" autofocus required>
                <label for="userPass"><?=$this->t('Password'); ?></label>
                <input size="30" type="password" id="userPass" name="userPass" placeholder="<?=$this->t('Password'); ?>" required>
            <?php endif; ?>
        </fieldset>
        <fieldset>
            <input type="hidden" name="_form_auth_redirect_to" value="<?=$this->e($_form_auth_redirect_to); ?>">
            <button type="submit"><?=$this->t('Sign In'); ?></button>
        </fieldset>
    </form>
<?php $this->stop(); ?>
