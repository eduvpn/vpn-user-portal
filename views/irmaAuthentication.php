<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">
    const irmaServerUrl = '<?php echo $this->e($session); ?>';
    const userIdAttribute = '<?php echo $this->e($userIdAttribute); ?>'
    const token = 'mysecrettoken'
    const irmaRequest = {
      "@context": "https://irma.app/ld/request/disclosure/v2",
      "disclose": [
        [
          [userIdAttribute],
        ]
      ]
    };

    document.addEventListener("DOMContentLoaded", function() {
      //Get the result and submit the form with the token as value
        function finishUp(result) {
            document.getElementById("sessionPointer").value = result;
            document.forms["myForm"].submit();
        }

        function verificate(pointer, sessionToken) {
            //IRMA front-end options
            const irmaFrontend = irma.newPopup({
                debugging: false,

                session: {
                    start: false,
                    mapping: {
                      sessionPtr: () => pointer
                    },
                    result: false
                }
            });
            irmaFrontend.start()
              .then(response => finishUp(sessionToken))
              .catch(error => console.error("Couldn't do what you asked", error));
        }
    });

</script>
<form id="myForm" method="post" action="<?php echo $requestRoot; ?>_irma/verify">
<input type="hidden" id="sessionPointer" name="irma_auth_token" value="TOKEN_FROM_JS">
</form>

<?php $this->stop('content'); ?>
