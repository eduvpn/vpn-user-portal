<?php declare(strict_types=1);
if (1 < count($supportedLanguages)): ?>
<ul class="languageSwitcher">
    <form class="languageSwitcher" method="post" action="<?=$this->e($requestRoot); ?>setLanguage">
<?php foreach ($supportedLanguages as $k => $v): ?>
        <li><button type="submit" name="setLanguage" value="<?=$this->e($k); ?>"><?=$this->e($v); ?></button></li>
<?php endforeach; ?>
    </form>
</ul>
<?php endif; ?>
