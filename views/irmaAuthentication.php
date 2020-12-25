<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<<<<<<< HEAD
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">

const irmaServerUrl = 'http://145.100.181.103:8088';

const irmaRequest = {
  "@context": "https://irma.app/ld/request/disclosure/v2",
  "disclose": [
    [
      ["pbdf.pbdf.email.email"],
    ]
  ]
};

window.onload = function() {
  let u = window.location.href;
  if (u.endsWith('/'))
    u = u.substring(0,u.length -1);
};

//Get the result and submit the form with the token as value
function finishUp(result) {
    document.getElementById("sessionPtr").value = result;
    document.forms["myForm"].submit();
}

function getSessionPtr() {
  fetch('http://145.100.181.103:8088/session', {
      method: 'POST', 
      headers : {
        'Content-Type': 'application/json',
        'Authorization': 'mysecrettoken'
      },
      body: JSON.stringify(irmaRequest)
    })
    .then(results => results.json())
    .then(data => {verificate(data.sessionPtr)})
    .catch(error => console.log(error));
}


  
//Let the user verificate their attribute
function verificate(pointer) {
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

  irmaFrontend.start()
    .then(response => finishUp(pointer))
    .catch(error => console.error("Couldn't do what you asked 😢", error)); 
} 


</script>
<button id="verification" onclick="getSessionPtr()">Verify attribute</button>
<form id="myForm" method="post" action="<?=$this->e($requestRoot.'/irma/verify');?>">
<input type="hidden" id="sessionPtr" name="irma_auth_token" value="TOKEN_FROM_JS">
=======
<!--
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?php echo $this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>
<script>
const irmaServerUrl = '<?php echo $this->e($irmaServerUrl); ?>';
const userIdAttribute = '<?php echo $this->e($userIdAttribute); ?>';
/*
    Put IRMA client code here
 */
</script>
<!-- verify the IRMA token obtained to complete the authentication -->
<form method="post" action="<?php echo $requestRoot; ?>_irma/verify">
    <input type="hidden" name="irma_auth_token" value="abc">
    <button type="submit">Verify</button>
>>>>>>> upstream/irma
</form>

<?php $this->stop('content'); ?>