<?php declare(strict_types=1);
$menuItems = [];
$menuItems['home'] = $this->t('Home');
if ($enableConfigDownload) {
    $menuItems['configurations'] = $this->t('Configurations');
}
$menuItems['account'] = $this->t('Account');
$menuItems['documentation'] = $this->t('Documentation');
if ($isAdmin) {
    $menuItems['connections'] = $this->t('Connections');
    $menuItems['users'] = $this->t('Users');
    $menuItems['info'] = $this->t('Info');
    $menuItems['stats'] = $this->t('Stats');
    $menuItems['log'] = $this->t('Log');
}
?>
<ul>
<?php foreach ($menuItems as $menuKey => $menuText): ?>
<?php if ($menuKey === $activeItem): ?>
    <li class="active">
<?php else: ?>
    <li>
<?php endif; ?>
        <a href="<?=$this->e($menuKey); ?>"><?=$menuText; ?></a>
    </li>
<?php endforeach; ?>
</ul>
