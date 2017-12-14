# Changelog

## 1.4.3 (2017-12-14)
- use 160 bits TOTP secret instead of 80 bits
- expose `user_id` in `/user_info` API call

## 1.4.2 (2017-12-12)
- cleanup autoloading
- hardcode the official eduVPN application registration for all platforms
- wrap `InputValidationException` in proper API responses when the API 
  calls triggered those exceptions
- update `eduvpn/common`

## 1.4.1 (2017-12-08)
- add 2FA enrollment to OAuth API

## 1.4.0 (2017-12-05)
- cleanup templates for easier extension and custom styling
  - breaks existing templates (falls back to default)
- implement page informing user to close the browser (after redirects to 
  native app only)
- mention uMatrix on documentation page (for advanced users)
- update `nl_NL` translation

## 1.3.2 (2017-11-30)
- support disabling approval for trusted OAuth clients
- rework (lib)sodium compatiblity

## 1.3.1 (2017-11-29)
- fix unit tests for `fkooman/oauth2-server` 2.0.1
  - OAuth server update fixes IE 11 support for the eduVPN for Windows 
    application

## 1.3.0 (2017-11-28)
- update `fkooman/oauth-client` to 
  [6.0.0](https://github.com/fkooman/php-oauth2-client/blob/master/CHANGES.md#600-2017-11-27)
- update LDAP authentication configuration examples

## 1.2.0 (2017-11-23)
- support LDAP authentication

## 1.1.1 (2017-11-20)
- support disabling compression

## 1.1.0 (2017.11-14)
- support PHPUnit 6
- update to `fkooman/oauth2-server` 2.0 ([CHANGES](https://github.com/fkooman/php-oauth2-server/blob/master/CHANGES.md#200-201711-14))
- allow updating branding/style using `styleName` configuration option

## 1.0.10 (2017-11-06)
- update documentation, recommend against jail breaking / rooting
- federated identity issuer can also contain numbers

## 1.0.9 (2017-10-26)
- support PHP 7.2 (sodium)
- refactor binary scripts

## 1.0.8 (2017-10-24)
- update iOS documentation, mention seamless tunnel
- update Linux documentation, mention various tested distributions

## 1.0.7 (2017-10-17)
- add Danish translation (provided by Tangui Coulouarn)
- update Documentation page (remove screenshot, 2FA updates)
- only have English as UI language by default as we have multiple languages 
  supported now, it does not make sense to favor Dutch

## 1.0.6 (2017-09-18)
- API call `user_info` also exposes `two_factor_enrolled_with` now to show 
  which 2FA methods the user is enrolled for

## 1.0.5 (2017-09-14)
- show "display name" of OAuth client instead of "client id" on the account 
  page (issue #75)

## 1.0.4 (2017-09-11)
- change session name to SID to get rid of explicit Domain binding;

## 1.0.3 (2017-09-11)
- update session handling:
  - (BUG) session cookie MUST expire at end of user agent session;
  - do not explicitly specify domain for cookie, this makes the 
    browser bind the cookie to actual domain and path;

## 1.0.2 (2017-09-10)
- update `fkooman/secookie`
- update default config file, no effect for deployed instances:
  - set OAuth access token expiry to 1 hour
  - remove old Android app as OAuth client

## 1.0.1 (2017-08-24)
- remove incomplete `de_DE` and `fr_FR` translations for now
- update configuration template
  - new default discovery URL
  - disable eduvpn.tuxed.net client by default

## 1.0.0 (2017-07-13)
- initial release
