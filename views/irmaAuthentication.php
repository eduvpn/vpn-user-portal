<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<!--
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?php echo $this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>
<script>
const sessionPtr = '<?php echo $sessionPtr; ?>';
/*
    Put IRMA client code here
 */
</script>
<!-- verify the IRMA token obtained to complete the authentication -->
<form method="post" action="<?php echo $requestRoot; ?>_irma/verify">
    <input type="hidden" name="irma_auth_token" value="abc">
    <button type="submit">Verify</button>
</form>
<?php $this->stop('content'); ?>
