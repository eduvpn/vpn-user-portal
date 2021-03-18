<?php declare(strict_types=1);
if ($_show_logout_button): ?>
<form class="logout" method="post" action="<?=$this->e($requestRoot); ?>_logout">
    <button type="submit"><?=$this->t('Sign Out'); ?></button>
</form>
<?php endif; ?>
