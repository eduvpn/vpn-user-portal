<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>" defer></script>

<script>

let options = {
      // Developer options
      debugging: true,

      // Front-end options
      language:  'en',
      translations: {
        header:  'Sign the agreement with <i class="irma-web-logo">IRMA</i>',
        loading: 'Just one second please!'
      },

      session: {
        // IRMA server:
        url: 'http://localhost:8088',

        // Start disclosure
        start: {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            "@context": "https://irma.app/ld/request/disclosure/v2",
            "disclose": [
              [
                [ "pbdf.pbdf.email.email" ]
              ]
            ]
          })
        }
      }
    };

    const irmaWeb = irma.newWeb({
      ...options,
      element: '#irma-web-form',
    });

    irmaWeb.start()
    .then(result => console.log("Successful disclosure! 🎉", result))
    .catch(error => {
      if (error === 'Aborted') {
        console.log('We closed it ourselves, so no problem 😅');
        return;
      }
      console.error("Couldn't do what you asked 😢", error);
    });

    let irmaPopup = irma.newPopup(options);
    document.getElementById('verification').onclick = () => {
      irmaPopup.start()
      .then(result => console.log("Successful disclosure! 🎉", result))
      .catch(error => {
        if (error === 'Aborted') {
          console.log('We closed it ourselves, so no problem 😅');
          return;
        }
        console.error("Couldn't do what you asked 😢", error);
      })
      .finally(() => irmaPopup = irma.newPopup(options));
    };
/*
function doSession(request) {
        clearOutput();
        showSuccess('Demo running...');

        const server = 'http://localhost:8088';
        const authmethod = token.value;
        const key = document.getElementById(authmethod === 'publickey' ? 'key-pem' : 'key').value;
        const requestorname = document.getElementById('requestor').value;

        return irma.startSession(server, request, authmethod, key, requestorname)
          .then(function(pkg) { return irma.handleSession(pkg.sessionPtr, {server: server, token: pkg.token, method: 'popup', language: 'en'}); })
          .then(function(result) {
            console.log('Done', result);
            return result;
          })
          .catch(function(err) { showError(err); });
      }

function doVerificationSession() {
        
        doSession(request).then(function(result) { showSuccess('Success, attribute value: <strong>' + result.disclosed[0][0].rawvalue + '</strong>'); });
 }

window.onload = function() {
        let u = window.location.href;
        if (u.endsWith('/'))
          u = u.substring(0, u.length - 1);
        //document.getElementById('server').value = u;
        //document.getElementById('verification').addEventListener('click', doVerificationSession);
      };

*/
</script>
<form>
    <label for="attr" class="label">Attribute:</label>
    <input id="server" type="text">
    <button id="verification">Verify attribute</button>
</form>
<?php $this->stop('content'); ?>
