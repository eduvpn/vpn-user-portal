<?php $this->layout('base', ['pageTitle' => $this->t('Two-factor Authentication')]); ?>
<?php $this->start('content'); ?>
    <div class="auth">
        <p>
            <?=$this->t('Please provide your TOTP.'); ?>
        </p>

        <?php if ($_two_factor_auth_invalid): ?>
            <p class="error">
                <?=$this->t('The TOTP you provided is incorrect.'); ?> (<?=$this->e($_two_factor_auth_error_msg); ?>)
            </p>
        <?php endif; ?>

        <form class="frm" method="post" action="<?=$this->e($requestRoot); ?>_two_factor/auth/verify/totp">
            <fieldset>
                    <label for="totpKey"><?=$this->t('OTP'); ?></label>
                    <input type="text" inputmode="numeric" placeholder="<?=$this->t('OTP'); ?>" id="totpKey" name="_two_factor_auth_totp_key" autocomplete="off" maxlength="6" required pattern="[0-9]{6}" autofocus>
            </fieldset>
            <fieldset>
                <input type="hidden" name="_two_factor_auth_redirect_to" value="<?=$this->e($_two_factor_auth_redirect_to); ?>">
                <button type="submit"><?=$this->t('Verify'); ?></button>
            </fieldset>
        </form>

        <p>
            <?=$this->t('Contact support if you lost your TOTP.'); ?>
            <?=$this->t('Your ID is <code>%_two_factor_user_id%</code>.'); ?>
        </p>
    </div>
<?php $this->stop('content'); ?>
