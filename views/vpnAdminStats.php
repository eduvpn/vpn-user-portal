<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */?>
<?php /** @var array<array{client_id:string,client_count:int,client_count_rel:float,client_count_rel_pct:int,slice_no:int,path_data:string}> $appUsage */?>
<?php /** @var array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList */?>
<?php /** @var array<string,array{max_connection_count:int}> $statsMaxConnectionCount */?>
<?php /** @var array<string,array{unique_user_count:int}> $statsUniqueUserCount */?>
<?php $this->layout('base', ['activeItem' => 'stats', 'pageTitle' => $this->t('Stats')]); ?>
<?php $this->start('content'); ?>
<h2><?=$this->t('Profile Usage'); ?></h2>
<p>
<?=$this->t('The table below shows the per profile VPN usage over the last week.'); ?>

<?=$this->t('You can download the detailed VPN usage statistics below.'); ?>
</p>
<table class="tbl">
<thead>
    <tr>
        <th><?=$this->t('Profile'); ?></th>
        <th title="<?=$this->t('The number of unique users connecting to the VPN service in the last week'); ?>"><?=$this->t('#Unique Users'); ?></th>
        <th title="<?=$this->t('The maximum number of simultaneously connected VPN clients at a particular moment in time over the last week'); ?>"><?=$this->t('Max #Connections'); ?></th>
        <th colspan="2"><?=$this->t('Export (CSV)'); ?></th>
    </tr>
</thead>
<tbody>
<?php foreach ($profileConfigList as $profileConfig): ?>
    <tr>
        <td><?=$this->e($profileConfig->displayName()); ?></td>
<?php if (!array_key_exists($profileConfig->profileId(), $statsUniqueUserCount)): ?>
        <td>0</td>
<?php else: ?>
        <td><?=$this->e((string) $statsUniqueUserCount[$profileConfig->profileId()]['unique_user_count']); ?></td>
<?php endif; ?>

<?php if (!array_key_exists($profileConfig->profileId(), $statsMaxConnectionCount)): ?>
        <td>0</td>
<?php else: ?>
        <td><?=$this->e((string) $statsMaxConnectionCount[$profileConfig->profileId()]['max_connection_count']); ?></td>
<?php endif; ?>
        <td>
            <form class="frm" method="get" action="csv_stats/live"><input type="hidden" name="profile_id" value="<?=$this->e($profileConfig->profileId()); ?>"><button title="<?=$this->t('#Connections in 5 minute intervals'); ?>"><?=$this->t('Live'); ?></button></form>
            </td>
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
