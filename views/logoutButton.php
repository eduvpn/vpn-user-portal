<?php if (!isset($_form_auth_login_page)): ?>
<form class="logout" method="post" action="<?=$this->e($requestRoot); ?>_logout">
    <button type="submit"><?=$this->t('Sign Out'); ?></button>
</form>
<?php endif; ?>
