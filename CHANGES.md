# Changelog

## 0.1.4
- handle errors with existing configurations before sending a request to the 
  certificate backend to avoid failed Guzzle requests

## 0.1.3
- update the default user inteface a little
- update to new `fkooman/rest`
- use `fkooman/rest-plugin-mellon` now for authentication
- update code for new dependencies

## 0.1.2
- implement CSRF fix
- add CSP and X-Frame-Options headers to default Apache config
 
## 0.1.1
- implement authentication configuration
- fix Guzzle function loading

## 0.1.0
- initial release
