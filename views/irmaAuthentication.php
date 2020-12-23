<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">

const irmaServerUrl = 'http://localhost:8088';

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
    document.getElementById("token").value = result;
    document.forms["myForm"].submit();
}

function getSessionPtr() {
  fetch('http://localhost:8088/session', {
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
    .then(response => finishUp(response.token))
    .catch(error => console.error("Couldn't do what you asked 😢", error)); 
} 


</script>
<button id="verification" onclick="getSessionPtr()">Verify attribute</button>
<form id="myForm" method="post" action="<?=$this->e($requestRoot.'/irma/verify');?>">
<input type="hidden" id="token" value="TOKEN_FROM_JS">
</form>

<?php $this->stop('content'); ?>