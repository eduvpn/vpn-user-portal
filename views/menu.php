<ul class="menu">
  <li <?php if ('new' === $activeItem): ?>class="active"<?php endif; ?>>          <a href="new"><?=$this->t('New'); ?></a></li>
  <li <?php if ('certificates' === $activeItem): ?>class="active"<?php endif; ?>> <a href="certificates"><?=$this->t('Certificates'); ?></a></li>
  <li <?php if ('account' === $activeItem): ?>class="active"<?php endif; ?>>      <a href="account"><?=$this->t('Account'); ?></a></li>
  <li <?php if ('documentation' === $activeItem): ?>class="active"<?php endif; ?>><a href="documentation"><?=$this->t('Documentation'); ?></a></li>
</ul>
