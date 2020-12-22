<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>

<script type="text/javascript">

const irmaServerUrl = 'http://localhost:8080';
const port = 8080;


const irmaRequest = {
  "@context": "https://irma.app/ld/request/disclosure/v2",
  "disclose": [
    [
      ["pbdf.pbdf.email.email"],
    ]
  ]
};

const irmaFrontend = irma.newPopup({
  debugging: true, 

  session: {
    url: "http://localhost:8088",
    
    start: {
      method: 'POST',
      headers : {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(irmaRequest)
    }
  }
});

var verified = false;
var value;

window.onload = function() {
  let u = window.location.href;
  if (u.endsWith('/'))
    u = u.substring(0,u.length -1);
  document.getElementById("verification").addEventListener("submit", function(e) {
    e.preventDefault();
  });
};

function finishUp(result) {
    verified = true;
    value = result;
}

function verificate() {
  irmaFrontend.start()
    .then(response => finishUp(response.disclosed[0][0].rawvalue))
    .catch(error => console.error("Couldn't do what you asked 😢", error)); 
}

function doSubmit() {
  return console.log(value);
}
  

irmaFrontend.abort();
</script>
<button id="verification" onclick="verificate()">Verify attribute</button>
<form>
<button id="sub" onsubmit="doSubmit();">Login</button>
</form>
<?php $this->stop('content'); ?>