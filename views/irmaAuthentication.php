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
    //  Send to the back-end that the QR-code has been scanned and the result can be retrieved from the IRMA server
        function finishUp(result) {
            document.getElementById("sessionPointer").value = result;
            document.forms["myForm"].submit();
        }

        function verificate() {
            //IRMA front-end configuration
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
          //Start the popup and show the QR-code
            irmaFrontend.start()
              .then(response => finishUp("SEND"))
              .catch(error => console.error("Couldn't do what you asked", error));
        }
    });

</script>
<form id="myForm" method="post" action="<?php echo $requestRoot; ?>_irma/verify">
<input type="hidden" id="sessionPointer" name="irma_auth_token" value="TOKEN_FROM_JS">
</form>

<?php $this->stop('content'); ?>
