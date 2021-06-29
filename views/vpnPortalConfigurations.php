<?php declare(strict_types=1); ?>
<?php /** @var \LC\Portal\Tpl $this */?>
<?php /** @var \DateTimeImmutable $expiryDate */?>
<?php /** @var array<string,\LC\Portal\ProfileConfig> $profileConfigList */?>
<?php /** @var array<array{profile_id:string,display_name:string,public_key:string,ip_four:string,ip_six:string,expires_at:\DateTimeImmutable,auth_key:?string}>|array<array{profile_id:string,common_name:string,display_name:string,expires_at:\DateTimeImmutable,auth_key:?string}> $userCertificateList */?>
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
            </fieldset>
            <fieldset>
                <button type="submit"><?=$this->t('Download'); ?></button>
            </fieldset>
        </form>
    <?php endif; ?>

    <?php if (0 !== count($userCertificateList)): ?>
        <h2><?=$this->t('Existing'); ?></h2>
        <table class="tbl">
            <thead>
                <tr><th><?=$this->t('Profile'); ?></th><th><?=$this->t('Name'); ?></th><th><?=$this->t('Expires'); ?></th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($userCertificateList as $userCertificate): ?>
<?php if (array_key_exists('common_name', $userCertificate)): ?>
                <!-- OpenVPN -->
                <tr>
                    <td><?=$this->e($userCertificate['profile_id']); ?></td>
                    <td><span title="<?=$this->e($userCertificate['display_name']); ?>"><?=$this->etr($userCertificate['display_name'], 25); ?></span></td>
                    <td><?=$this->d($userCertificate['expires_at']); ?></td>
                    <td class="text-right">
                        <form class="frm" method="post" action="deleteOpenVpnConfig">
                            <input type="hidden" name="commonName" value="<?=$this->e($userCertificate['common_name']); ?>">
                            <button class="warning" type="submit"><?=$this->t('Delete'); ?></button>
                        </form>
                    </td>
                </tr>
<?php endif; ?>
<?php if (array_key_exists('public_key', $userCertificate)): ?>
                <!-- WireGuard -->
                <tr>
                    <td><?=$this->e($userCertificate['profile_id']); ?></td>
                    <td><span title="<?=$this->e($userCertificate['display_name']); ?>"><?=$this->etr($userCertificate['display_name'], 25); ?></span></td>
                    <td><?=$this->d($userCertificate['expires_at']); ?></td>
                    <td class="text-right">
                        <form class="frm" method="post" action="deleteWireGuardConfig">
                            <input type="hidden" name="profileId" value="<?=$this->e($userCertificate['profile_id']); ?>">
                            <input type="hidden" name="publicKey" value="<?=$this->e($userCertificate['public_key']); ?>">
                            <button class="warning" type="submit"><?=$this->t('Delete'); ?></button>
                        </form>
                    </td>
                </tr>
<?php endif; ?>
<?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php $this->stop('content'); ?>
