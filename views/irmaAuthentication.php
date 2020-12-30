<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<<<<<<< HEAD
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

=======
<!--
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?php echo $this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>
<script>
const sessionPtr = '<?php echo $this->e($sessionPtr); ?>';
/*
    Put IRMA client code here
 */
>>>>>>> bf188b4748f094da4d029fbeb6ed5ce806895cd9
</script>
<form id="myForm" method="post" action="<?php echo $requestRoot; ?>_irma/verify">
<input type="hidden" id="sessionPointer" name="irma_auth_token" value="TOKEN_FROM_JS">
</form>

<?php $this->stop('content'); ?>
