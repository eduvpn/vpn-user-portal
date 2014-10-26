# Changelog

## 0.1.11
- fix PHP 5.3 support

## 0.1.10
- return to Guzzle 3.x support for CentOS / Red Hat Enterprise 6 support

## 0.1.9
- really fix `bin/vpn-user-portal-init`

## 0.1.8
- fix `bin/vpn-user-portal-init`
- update spec file making apache the owner of the config file

## 0.1.7
- update to new `fkooman/ini` API

## 0.1.6
- use new `fkooman/ini` instead of `fkooman/config`
- update coding standards

## 0.1.5
- update `fkooman/json` and `fkooman/config` dependencies
- increase padding in UI

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
