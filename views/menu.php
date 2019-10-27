<ul>
<?php foreach (['home' => $this->t('Home'), 'configurations' => $this->t('Configurations'), 'account' => $this->t('Account'), 'documentation' => $this->t('Documentation')] as $menuKey => $menuText): ?>
<?php if ($menuKey === $activeItem): ?>
    <li class="active">
<?php else: ?>
    <li>
<?php endif; ?>
        <a href="<?=$this->e($menuKey); ?>"><?=$menuText; ?></a>
    </li>
<?php endforeach; ?>
<?php if ($isAdmin): ?>
<?php foreach (['connections' => $this->t('Connections'), 'users' => $this->t('Users'), 'info' => $this->t('Info'), 'stats' => $this->t('Stats'), 'messages' => $this->t('Messages'), 'log' => $this->t('Log')] as $menuKey => $menuText): ?>
<?php if ($menuKey === $activeItem): ?>
    <li class="active">
<?php else: ?>
    <li>
<?php endif; ?>
        <a href="<?=$this->e($menuKey); ?>"><?=$menuText; ?></a>
    </li>
<?php endforeach; ?>
<?php endif; ?>
</ul>
