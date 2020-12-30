<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">
const irmaServerUrl = '<?php echo $this->e($irmaServerUrl); ?>';
const userIdAttribute = '<?php echo $this->e($userIdAttribute); ?>'
const token = '<?php echo $this->e($token); ?>'
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

    fetch(irmaServerUrl + '/session', {
        method: 'POST',
        headers : {
          'Content-Type': 'application/json',
          'Authorization': token
        },
        body: JSON.stringify(irmaRequest)
      })
      .then(results => results.json())
      .then(data => {verificate(data.sessionPtr, data.token)})
      .catch(error => console.log(error));

    function verificate(pointer, token) {
        //IRMA front-end options
        const irmaFrontend = irma.newPopup({
          debugging: true,

          session: {
            start: false,
            mapping: {
              sessionPtr: () => pointer
            },
            result: false
          }
        });
    }
});



  irmaFrontend.start()
    .then(response => finishUp(token))
    .catch(error => console.error("Couldn't do what you asked 😢", error));
}


</script>
<button id="verification" onclick="getSessionPtr()">Verify attribute</button>
<form id="myForm" method="post" action="<?php echo $requestRoot; ?>_irma/verify">
<input type="hidden" id="sessionPointer" name="irma_auth_token" value="TOKEN_FROM_JS">
</form>

<?php $this->stop('content'); ?>
