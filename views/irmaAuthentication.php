<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<script src="<?=$this->getAssetUrl($requestRoot, 'js/irma.js'); ?>" defer></script>
<script>
function doSession(request) {
        clearOutput();
        showSuccess('Demo running...');

        const server = document.getElementById('server').value;
        const authmethod = document.getElementById('method').value;
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
        const attr = document.getElementById('attr').value;
        const label = document.getElementById('label').value;
        const message = document.getElementById('message').value;
        const labelRequest = !label ? {} : {'labels': {'0': {'en': label, 'nl': label}}};
        const request = !message ? {
          '@context': 'https://irma.app/ld/request/disclosure/v2',
          'disclose': [
            [
              [ pbdf.pbdf.email.email ]
            ]
          ],
        } : {
          '@context': 'https://irma.app/ld/request/signature/v2',
          'message': message,
          'disclose': [
            [
              [ pbdf.pbdf.email.email ]
            ]
          ],
        };
        doSession(request).then(function(result) { showSuccess('Success, attribute value: <strong>' + result.disclosed[0][0].rawvalue + '</strong>'); });
 }

window.onload = function() {
        let u = window.location.href;
        if (u.endsWith('/'))
          u = u.substring(0, u.length - 1);
        document.getElementById('server').value = u;
        document.getElementById('verification').addEventListener('click', doVerificationSession);
      };
</script>
<form>
    <label for="attr" class="label">Attribute:</label>
    <input id="attr" type="text" value="pbdf.pbdf.email.email"><br/>
    <input id="server" type="text">
    <button id="verification">Verify attribute</button>
</form>
<?php $this->stop('content'); ?>
