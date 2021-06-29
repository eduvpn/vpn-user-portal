<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var array<array{client_id:string,client_count:int,client_count_rel:float,client_count_rel_pct:int,slice_no:int,path_data:string}> $appUsage */?>
<?php $this->layout('base', ['activeItem' => 'stats', 'pageTitle' => $this->t('Stats')]); ?>
<?php $this->start('content'); ?>
<?php if (0 !== count($appUsage)): ?>
<h2><?=$this->t('Application Usage'); ?></h2>
<figure>
    <svg class="appUsage" xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 2 2" style="transform: rotate(-90deg)">
<?php foreach ($appUsage as $appInfo): ?>
        <path class="pieColor<?=$this->e((string) ($appInfo['slice_no'] + 1)); ?>" d="<?=$this->e($appInfo['path_data']); ?>"/>
<?php endforeach; ?>
    </svg>
<figcaption>
<?=$this->t('Distribution of unique users over the VPN applications.'); ?>
    <ul class="appUsage">
<?php foreach ($appUsage as $appInfo): ?>
        <li>
            <span title="<?=$this->e((string) $appInfo['client_count']); ?>" class="pieLegend pieColor<?=$this->e((string) ($appInfo['slice_no'] + 1)); ?>"><?=$this->e((string) $appInfo['client_count_rel_pct']); ?>%</span><?=$this->clientIdToDisplayName($appInfo['client_id']); ?>
        </li>
<?php endforeach; ?>
    </ul>
</figcaption>
</figure>
<?php endif; ?>
<?php $this->stop('content'); ?>
