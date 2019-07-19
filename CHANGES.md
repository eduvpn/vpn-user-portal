# Changelog

## 2.0.7 (...)
- add Ubuntu 18.04 Roboto font path (issue #135)
- make the log work with local timezone instead of UTC (issue #137)
- do not allow to specify a time in the future on log page (issue #136)
- update `nl_NL` and `nb_NO` translations

## 2.0.6 (2019-06-27)
- also show maximum number of concurrent connections possible per profile 
  (issue #133)
- fix rounding problem in traffic graph on stats page (issue #134)

## 2.0.5 (2019-06-07)
- proper input validation on value from language cookie

## 2.0.4 (2019-05-21)
- fix default language for portal, it always used English independent of the
  order of languages in the configuration file
- fix `MellonAuthentication` logout

## 2.0.3 (2019-05-14)
- update Norwegian translation
- fix issue HTTP 500 API response when token was not JWT token (failed to 
  extract key ID) (eduvpn/macos#217, eduvpn/vpn-server-api#75)

## 2.0.2 (2019-05-01)
- do not list hidden profile in `/profile_list` API call

## 2.0.1 (2019-04-26)
- update tests to deal with updates internal API error messages 
  (vpn-lib-common)
- fix bug where 2FA documentation was shown even when 2FA is disabled (PR #122)

## 2.0.0 (2019-04-01)
- remove PHP error suppression
- rework 2FA enrollment, enrollment goes through `/two_factor_enroll` now
- no longer show which profiles have 2FA enabled, no longer relevant as
  2FA through OpenVPN will be removed in the near future
- update 2FA documentation
- no longer ask for confirmation when deleting a certificate (#107)
- remove VOOT support
- force user to enroll for 2FA when 2FA is required
- remove YubiKey support
- only show certificates manually issued on "Certificates" page, not the ones 
  issued to OAuth clients
- rewrite `ForeignKeyListFetcher` to no longer require `fkooman/oauth2-client`
- remove `/create_config` (was not used by any client anymore)
- lie about 2FA to the API client so client never will try to enroll the user
- no longer use `display_name` parameter for `/create_keypair`, just use the 
  OAuth `client_id` of the client as display name
- implement database migration support for OAuth tokens
- remove user registration with vouchers for `PdoAuth`
- remove Twig, switch to Tpl
- remove compression framing support
- remove tls-auth support
- remove "multi instance" support
- update to version 4 of `fkooman/oauth2-server`
- (re)introduce admin permission based on userId
- rename "entitlement" to "permission"
- OAuth key moved to configuration directory instead of data directory
- implement `SamlAuthentication` support (php-saml-sp)
- implement `ShibAuthentication` support (Shibboleth SP)
- implement user session expiry

## 1.8.5 (2018-11-28)
- no longer allow clients to obtain new access tokens using the refresh token 
  when the user account is disabled
- add ability to disable 2FA or select which methods are supported
  - new default is TOTP only
  - YubiKey OTP is **DEPRECATED**
- implement SAML logout for `MellonAuthentication`

## 1.8.4 (2018-11-22)
- use `UserInfo::authTime`. Obtain it from the `last_authenticated_at` 
  information retained by the server in case of API requests
- synchronize expiry of browser session, X.509 client certificate and OAuth 
  refresh_token so they all expire at the same time
- remove `Api/refreshTokenExpiry` from config template, replaced by 
  `sessionExpiry`
- remove `Api/tokenExpiry` from config template, overriding it will still 
  work, but don't advertise this
- remove "Entitlements" from Account page, merged with "Group Membership(s)"
- add "VOOT membership" retrieval through "frontend". Deprecates the VOOT 
  backend handling through vpn-server-api and making VOOT integration more 
  robust

## 1.8.3 (2018-10-15)
- drop support for OpenVPN 2.3 clients

## 1.8.2 (2018-10-10)
- update `da_DK` translation (by Sanne Holm)
- also consider entitlements when showing list of profiles
- cache entitlements on the server as well as part of `LastAuthenticatedAtPing`

## 1.8.1 (2018-09-10)
- update for new vpn-lib-common API
- cleanup autoloader so Psalm will be able to verify the scripts in web and bin
  folder
- when creating a certificate through the API, bind it to the OAuth client ID
- delete all certificates associated with OAuth client ID when revoking OAuth
  application on "Account" page and disconnect the clients using certificates 
  issued to this client (issue #89)
- rename "Configurations" to "Certificates" as that better covers what this 
  page is actually about
- update `nl_NL` translations
- add `fr_FR` translation (by Tangui Coulouarn)

## 1.8.0 (2018-08-15)
- use new authorization method

## 1.7.2 (2018-08-05)
- many `vimeo/psalm` fixes
- code refactors to make it better verifyable

## 1.7.1 (2018-07-24)
- convert errors from backend into proper API responses instead of HTTP/500 
  responses to API client (#95)

## 1.7.0 (2018-07-02)
- record the last time the user authenticated
- certificates can no longer be disabled, so no need to show this any longer, 
  also remove it as a reason from `/check_certificate` API call

## 1.6.10 (2018-06-06)
- support `tlsProtection`

## 1.6.9 (2018-05-24)
- support optional "customFooter" template

## 1.6.8 (2018-05-22)
- add support for Android "app links" for OAuth clients
- enable logging in OAuth client

## 1.6.7 (2018-05-17)
- update nl_NL translations

## 1.6.6 (2018-05-16)
- show the user ID on the TOTP/YubiKey page when authenticating

## 1.6.5 (2018-05-11)
- add Let's Connect for Android as an OAuth client registration

## 1.6.4 (2018-05-03)
- convert spaces in `_` when downloading an OpenVPN configuration through the
  portal, fixes import in NetworkManager (Linux) (#92)
- return `reason` through API when checking validity of certificate

## 1.6.3 (2018-04-20)
- fix `/check_certificate` response to match API (#32)

## 1.6.2 (2018-04-12)
- update for `fkooman/oauth2-client` version 7

## 1.6.1 (2018-03-29)
- implement `/check_certificate` API call
- support multiple RADIUS servers

## 1.6.0 (2018-03-19)
- update for `fkooman/oauth2-server` API changes

## 1.5.4 (2018-05-16)
- support RADIUS for user authentication

## 1.5.3 (2018-03-15)
- switch to `UserInfo` class
- add extra redirect URIs for iOS client

## 1.5.2 (2018-02-28)
- make sure chosen userId does not exist yet when registering a new account
  (avoiding database constraint exception)
- script to generate voucher now prints voucherCode
- input validation for user chosen passwords when changing passwords and
  registering new accounts
- rework exposing proto/port in client configuration
- make sure data directory exists before adding users

## 1.5.1 (2018-02-26)
- remove `addVpnProtoPorts` configuration option, and switch to 
  `exposedVpnProtoPorts` in Server API

## 1.5.0 (2018-02-25)
- support `FormPdoAuthentication` and make it the default
- deprecate `FormAuthentcation`, new deploys will use `FormPdoAuthentication` 
  by default
- implement support for changing passwords by users when using 
  `FormPdoAuthentication`
- implement user self registration with vouchers
- if `tlsCrypt` is enabled, use `AES-256-GCM` as only supported cipher

## 1.4.9 (2018-02-06)
- set default for refresh token expiry to 180 days (instead of 6 months to sync
  with default of CA certificates)
- add Let's Connect OAuth client registration for Windows

## 1.4.8 (2018-01-24)
- update Norwegian translation

## 1.4.7 (2018-01-22)
- update authorization dialog text

## 1.4.6 (2018-01-10)
- support refresh token expiry (update `fkooman/oauth2-server`)
- set default for refresh token expiry to 6 months

## 1.4.5 (2017-12-28)
- no longer show "Scope" for authorized applications
- translate "Enroll" on account page
- do not show group information when there are no groups to show (issue #85)
- do not show authorized applications when there are none

## 1.4.4 (2017-12-23)
- simplify OAuth consent dialog
- reenable "Approval" dialog for OAuth clients for now
- make add-user script interactive if no `--user` or `--pass` CLI parameters
  are specified (issue #83)

## 1.4.3 (2017-12-14)
- use 160 bits TOTP secret instead of 80 bits
- expose `user_id` in `/user_info` API call
- update Tunneblick documentation

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
