<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<!--
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">
  //Get the session pointer from the back-end
    const sessionPtr = '<?php echo $sessionPtr; ?>';

    document.addEventListener("DOMContentLoaded", function() {
        // IRMA front-end configuration
        const irmaFrontend = irma.newPopup({
            debugging: false,

            session: {
                start: false,
                mapping: {
                  sessionPtr: () => JSON.parse(sessionPtr)
                },
                result: false
            }
        });
        // Start the popup and show the QR-code
        irmaFrontend.start()
          .then(response => document.forms["authentication"].submit())
          .catch(error => console.error("Couldn't do what you asked", error));
    });

</script>
<form id="authentication" method="post" action="<?php echo $requestRoot; ?>_irma/verify">
</form>

<?php $this->stop('content'); ?>
