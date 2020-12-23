<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<!-- 
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist 
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>
<script>
/*
    Put IRMA client code here
*/
</script>
<?php $this->stop('content'); ?>
