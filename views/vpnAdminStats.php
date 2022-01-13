<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<array{client_id:string,client_count:int,client_count_rel:float,client_count_rel_pct:int,slice_no:int,path_data:string}> $appUsage */?>
<?php /** @var array<\Vpn\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<string,int> $maxConnectionCountList */?>
<?php /** @var array<string,int> $uniqueUsersCountList */?>
<?php $this->layout('base', ['activeItem' => 'stats', 'pageTitle' => $this->t('Stats')]); ?>
<?php $this->start('content'); ?>
<h2><?=$this->t('Profile Usage'); ?></h2>
<table class="tbl">
<thead>
    <tr>
        <th><?=$this->t('Profile'); ?></th>
        <th><?=$this->t('#Unique Users'); ?></th>
        <th><?=$this->t('Max #Active Connections'); ?></th>
        <th></th>
    </tr>
</thead>
<tbody>
<?php foreach ($profileConfigList as $profileConfig): ?>
    <tr>
        <td><?=$this->e($profileConfig->displayName()); ?></td>
<?php if (!array_key_exists($profileConfig->profileId(), $uniqueUsersCountList)): ?>
        <td>0</td>
<?php else: ?>
        <td><?=$this->e((string) $uniqueUsersCountList[$profileConfig->profileId()]); ?></td>
<?php endif; ?>
<?php if (!array_key_exists($profileConfig->profileId(), $maxConnectionCountList)): ?>
        <td>0</td>
<?php else: ?>
        <td><?=$this->e((string) $maxConnectionCountList[$profileConfig->profileId()]); ?></td>
<?php endif; ?>
        <td><a href="csv_stats?profile_id=<?=$this->e($profileConfig->profileId()); ?>"><?=$this->t('CSV'); ?></a></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
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
