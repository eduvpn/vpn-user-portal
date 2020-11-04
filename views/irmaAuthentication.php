<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>" defer></script>

<script>

const server = 'http://localhost:8088';
const request = {
  '@context': 'https://irma.app/ld/request/disclosure/v2',
  'disclose': [
    [
      ['irma-deom.MijnOverheid.ageLower.over18']
    ]
  ]
};
IRMA.init("https://demo.irmacard.org/tomcat/irma_api_server/api/v2/");
            var sprequest = {
                "request": {
                    "content": [
                        {
                            "label": "18+",
                            "attributes": ["irma-demo.MijnOverheid.ageLower.over18"]
                        },
                    ]
                }
            };
            var success = function(jwt) { console.log("Success:", jwt); alert("Success"); }
            var warning = function() { console.log("Warning:", arguments); }
            var error = function() { console.log("Error:", arguments); }
            
window.onload = function() {
  let u = window.location.href;
  if (u.endsWith('/'))
    u = u.substring(0,u.length -1);
    document.getElementById("verification").addEventListener("click", function() {
    irma.startSession(server, request).then(({ sessionPtr, token} ) => irma.handleSession(sessionPtr, {server, token, method:'console'}))
      .then(result => console.log('Done', result)).catch(function(err) { alert("failed");});
      var jwt = IRMA.createUnsignedVerificationJWT(sprequest);
      IRMA.verify(jwt, success, warning, error);
   });
  };

</script>
<form>
    <button id="verification">Verify attribute</button>
</form>
<?php $this->stop('content'); ?>