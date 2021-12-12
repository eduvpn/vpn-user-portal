<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var string $uiLanguage */ ?>
<?php /** @var string $pageTitle */ ?>
<?php /** @var string $requestRoot */ ?>
<?php /** @var string $portalHostname */ ?>
<!DOCTYPE html>

<html lang="<?=$this->e($uiLanguage); ?>" dir="<?=$this->textDir(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=$this->t('VPN Portal'); ?> - <?=$this->e($pageTitle); ?></title>
    <link href="<?=$this->getAssetUrl($requestRoot, 'css/screen.css'); ?>" media="screen" rel="stylesheet">
    <!-- h: <?=$this->e($portalHostname); ?> -->
</head>
<body>
    <header class="page">
        <?=$this->insert('languageSwitcher'); ?>
        <?=$this->insert('logoutButton'); ?>
    </header>
    <nav>
<?php if (isset($activeItem)) : ?>
<?=$this->insert('menu', ['activeItem' => $activeItem]); ?>
<?php endif; ?>
    </nav>
    <header class="main">
        <h1><?=$this->e($pageTitle); ?></h1>
    </header>
    <main>
<?=$this->section('content'); ?>
    </main>
    <footer>
<?php if ($this->exists('customFooter')) : ?>
    <?=$this->insert('customFooter'); ?>
<?php endif; ?>
    </footer>
</body>
</html>
