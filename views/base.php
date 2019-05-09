<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width; height=device-height; initial-scale=1">
    <title><?=$this->t('VPN Portal'); ?></title>
    <link href="<?=$this->e($requestRoot); ?>css/normalize.css" media="screen" rel="stylesheet">
    <link href="<?=$this->e($requestRoot); ?>css/screen.css" media="screen" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
<?php if (isset($activeItem)): ?>
            <?=$this->insert('menu', ['activeItem' => $activeItem]); ?>
<?php endif; ?>
        </nav>
    </header>
    <main>
        <?=$this->section('content'); ?>
    </main>
    <footer>
<?php if ($this->exists('customFooter')): ?>
        <?=$this->insert('customFooter'); ?>
<?php endif; ?>
<?php if (1 < count($supportedLanguages)): ?>
        <form method="post" action="<?=$this->e($requestRoot); ?>setLanguage">
<?php foreach ($supportedLanguages as $k => $v): ?>
            <button name="setLanguage" value="<?=$this->e($k); ?>"><?=$this->e($v); ?></button>
<?php endforeach; ?>
        </form>
<?php endif; ?>
<?php if (!isset($_form_auth_login_page)): ?>
        <form method="post" action="<?=$this->e($requestRoot); ?>_logout">
            <button type="submit"><?=$this->t('Sign Out'); ?></button>
        </form>
<?php endif; ?>
    </footer>
</body>
</html>
