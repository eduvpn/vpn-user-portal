<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var \DateTimeImmutable $expiryDate */?>
<?php /** @var array<\LC\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<array{profile_id:string,display_name:string,profile_display_name:string,expires_at:\DateTimeImmutable,public_key:?string,common_name:?string}> $configList */?>
<?php $this->layout('base', ['activeItem' => 'configurations', 'pageTitle' => $this->t('Configurations')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('New'); ?></h2>
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

        <form method="post" class="frm">
            <fieldset>
                <label for="profileId"><?=$this->t('Profile'); ?></label>
                <select name="profileId" id="profileId" size="<?=count($profileConfigList); ?>" required>
<?php foreach ($profileConfigList as $profileConfig): ?>
                    <option value="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></option>
<?php endforeach; ?>
                </select>
                <label for="displayName"><?=$this->t('Name'); ?></label>
                <input type="text" name="displayName" id="displayName" size="32" maxlength="64" placeholder="<?=$this->t('Name'); ?>" autofocus required>
                <label for="tcpOnly"><input type="checkbox" id="tcpOnly" name="tcpOnly"> <?=$this->t('Connect only over TCP (OpenVPN)'); ?></label>
            </fieldset>
            <fieldset>
                <button type="submit"><?=$this->t('Download'); ?></button>
            </fieldset>
        </form>
    <?php endif; ?>

<?php if (0 !== count($configList)): ?>
        <h2><?=$this->t('Active'); ?></h2>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Profile'); ?></th><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires'); ?></th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($configList as $configItem): ?>
                <tr>
                    <td><span title="<?=$this->e($configItem['profile_id']); ?>"><?=$this->e($configItem['profile_display_name']); ?></span></td>
                    <td><span title="<?=$this->e($configItem['display_name']); ?>"><?=$this->etr($configItem['display_name'], 25); ?></span></td>
                    <td><?=$this->d($configItem['expires_at']); ?></td>
                    <td class="text-right">
                        <form class="frm" method="post" action="deleteConfig">
                            <input type="hidden" name="profileId" value="<?=$this->e($configItem['profile_id']); ?>">
<?php if (null !== $configItem['common_name']): ?>
                            <input type="hidden" name="commonName" value="<?=$this->e($configItem['common_name']); ?>">
<?php endif; ?>
<?php if (null !== $configItem['public_key']): ?>
                            <input type="hidden" name="publicKey" value="<?=$this->e($configItem['public_key']); ?>">
<?php endif; ?>
                            <button class="warning" type="submit"><?=$this->t('Delete'); ?></button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop('content'); ?>
