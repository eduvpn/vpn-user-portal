<?php declare(strict_types=1); ?>
<?php /** @var \Vpn\Portal\Tpl $this */ ?>
<?php /** @var ?int $maxActiveConfigurations */ ?>
<?php /** @var int $numberOfActivePortalConfigurations */ ?>
<?php /** @var \DateTimeImmutable $expiryDate */?>
<?php /** @var array<\Vpn\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<array{profile_id:string,display_name:string,expires_at:\DateTimeImmutable,connection_id:string}> $configList */?>
<?php $this->layout('base', ['activeItem' => 'home', 'pageTitle' => $this->t('Home')]); ?>
<?php $this->start('content'); ?>

<p class="lead"><?= $this->t('Welcome to this VPN service!'); ?></p>
    <h2><?=$this->t('New Configuration'); ?></h2>
<?php if (0 === $maxActiveConfigurations): ?>
        <p class="warning">
            <?=$this->t('Manually downloading VPN configurations is not allowed by your VPN administrator. Please use a VPN application.'); ?>
        </p>
<?php elseif (null !== $maxActiveConfigurations && $numberOfActivePortalConfigurations >= $maxActiveConfigurations): ?>
        <p class="warning">
            <?=$this->t('You have reached the maximum number of allowed VPN configuration downloads. Please delete some existing ones first.'); ?>
        </p>
<?php else: ?>
    <?php if (0 === count($profileConfigList)): ?>
        <p class="warning">
            <?=$this->t('No VPN profiles are available for your account.'); ?>
        </p>
    <?php else: ?>
		<p>
        <?=$this->t('Obtain a new VPN configuration file for use in your VPN client.'); ?>
		<?=$this->t('Select a profile and choose a name, e.g. "Phone".'); ?>
		</p>

        <p class="warning">
		<?=$this->t('Your new configuration will expire on %expiryDate%. Come back here to obtain a new configuration after expiry!'); ?>
		</p>

        <form method="post" action="addConfig" class="frm">
            <fieldset>
                <label for="profileId"><?=$this->t('Profile'); ?></label>
                <select name="profileId" id="profileId" size="<?=min(10, count($profileConfigList)); ?>" required>
<?php foreach ($profileConfigList as $profileConfig): ?>
                    <option value="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></option>
<?php endforeach; ?>
                </select>
                <label for="displayName"><?=$this->t('Name'); ?></label>
                <input type="text" name="displayName" id="displayName" size="32" maxlength="64" placeholder="<?=$this->t('Name'); ?>" autofocus required>
                <details>
                    <summary><?=$this->t('Advanced'); ?></summary>
                    <fieldset>
                        <label for="vpnProto"><?=$this->t('Use Protocol'); ?></label>
                        <select name="vpnProto" id="vpnProto">
                            <option value="default" selected><?=$this->t('Default'); ?></option>
                            <option value="openvpn"><?=$this->t('OpenVPN'); ?></option>
                            <option value="wireguard"><?=$this->t('WireGuard'); ?></option>
                        </select>
                    </fieldset>
                    <input type="checkbox" id="tcpOnly" name="tcpOnly"> <label for="tcpOnly"><?=$this->t('Connect over TCP (only OpenVPN)'); ?></label>
                </details>
            </fieldset>
            <fieldset>
                <button type="submit"><?=$this->t('Download'); ?></button>
            </fieldset>
        </form>
    <?php endif; ?>
<?php endif; ?>

        <h2><?=$this->t('Active Configurations'); ?></h2>
<?php if (0 === count($configList)): ?>
    <p class="plain">
            <?=$this->t('Currently no active configurations.'); ?>
    </p>
<?php else: ?>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Profile'); ?></th><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires On'); ?></th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($configList as $configItem): ?>
                <tr>
                    <td>
                        <span title="<?=$this->e($configItem['profile_id']); ?>"><?=$this->profileIdToDisplayName($profileConfigList, $configItem['profile_id']); ?></span>
                    </td>
                    <td><span title="<?=$this->e($configItem['display_name']); ?>"><?=$this->etr($configItem['display_name'], 25); ?></span></td>
                    <td><span title="<?=$this->d($configItem['expires_at']); ?>"><?=$this->d($configItem['expires_at'], 'Y-m-d'); ?></span></td>
                    <td class="text-right">
                        <form class="frm" method="post" action="deleteConfig">
                            <input type="hidden" name="profileId" value="<?=$this->e($configItem['profile_id']); ?>">
                            <input type="hidden" name="connectionId" value="<?=$this->e($configItem['connection_id']); ?>">
                            <button class="warning" type="submit"><?=$this->t('Delete'); ?></button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop('content'); ?>
