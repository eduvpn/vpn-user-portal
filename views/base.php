<!DOCTYPE html>

<html lang="<?=$this->e(str_replace('_', '-', $uiLang)); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!isset($activeItem)): ?>
    <title><?=$this->t('VPN Portal'); ?></title>
<?php else: ?>
    <title><?=$this->t('VPN Portal'); ?> - <?=$this->t(ucfirst($activeItem)); ?></title>
<?php endif; ?>
    <link href="<?=$this->e($requestRoot); ?>css/bootstrap-reboot.min.css" media="screen" rel="stylesheet">
    <link href="<?=$this->e($requestRoot); ?>css/screen.css" media="screen" rel="stylesheet">
</head>
<body>
    <header>			
        <?=$this->insert('languageSwitcher'); ?>
    </header>
    <div class="page">
        <nav>
<?php if (isset($activeItem)): ?>
            <?=$this->insert('menu', ['activeItem' => $activeItem]); ?>
<?php endif; ?>
        	<?=$this->insert('logoutButton'); ?>
        </nav>
        <main>
            <?=$this->section('content'); ?>
        </main>
    </div> <!-- /page -->
    <footer>
<?php if ($this->exists('customFooter')): ?>
    <?=$this->insert('customFooter'); ?>
<?php endif; ?>
    </footer>
</body>
</html>
