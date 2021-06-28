<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php if (1 < count($enabledLanguages)): ?>
    <ul class="languageSwitcher">
        <form class="languageSwitcher" method="post" action="<?=$this->e($requestRoot); ?>setLanguage">
<?php foreach ($enabledLanguages as $uiLanguage): ?>
            <li><button type="submit" name="uiLanguage" value="<?=$this->e($uiLanguage); ?>"><?=$this->e($this->languageCodeToHuman($uiLanguage)); ?></button></li>
<?php endforeach; ?>
        </form>
    </ul>
<?php endif; ?>
