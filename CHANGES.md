# Changelog

## 9.3.2 (2016-07-14)
- work with new group handling in vpn-server-api

## 9.3.1 (2016-07-09)
- again show the revoked configurations in the user portal, although
  configurations can no longer be revoked, we do not want them to 
  be completely gone from the UI

## 9.3.0 (2016-07-09)
- no longer offer to revoke configurations, only disable as we phase out 
  revocation completely

## 9.2.0 (2016-06-07)
- add optional SURFconext Voot support

## 9.1.1 (2016-05-27)
- fix API
- better error handling when somehow a non exiting pool is used

## 9.1.0 (2016-05-26)
- update for new API

## 9.0.2 (2016-05-23)
- update way in which the `remote` line in the config is sorted

## 9.0.1 (2016-05-19)
- fix OAuth API

## 9.0.0 (2016-05-18)
- remove ZIP download
- update to new vpn-server-api API
- update documentation
- implement "Pool" chooser if more than one pool is available

## 8.3.1 (2016-05-10)
- fix remote API
- do not allow empty OTP code when enrolling

## 8.3.0 (2016-05-06)
- new UI to change languages
- also allow changing languages when query parameters are present 

## 8.2.1 (2016-05-04)
- add some additional HTTP security headers
- update translation 

## 8.2.0 (2016-04-27)
- update to new API
- implement 2FA enrollment
- fix auto capitalization for iOS 
- lots of translation updates

## 8.1.1 (2016-04-20)
- update default VPN server list to also include udp/1195 and udp/1196
- randomize the remote list in the generated client config to get some 
  rudimentary load balancing

## 8.1.0 (2016-04-19)
- no longer use Twig template for client configuration
- allow configuring remote hosts in `config/config.yaml`
- sync crypto config with server

## 8.0.0 (2016-04-13)
- remove all 'pool' information from the UI
- add `nl_NL` translation
- update dependencies

## 7.0.0 (2016-03-30)
- replace complete API with OAuth 2.0 API
- remove QR code
- add Account page for listing user ID, applications that have tokens
  and group membership
- remove `whoami` endpoint, now available on account page

## 6.1.3 (2016-03-24)
- remove `fkooman/io` dependency and use `paragonie/random_compat` 
  instead for random number generation

## 6.1.2 (2016-03-18)
- remove the default user from the configuration file
- add an `add-user` script to easily add users
- update documentation

## 6.1.1 (2016-03-07)
- small fix in default pool

## 6.1.0 (2016-03-07)
- support new info API

## 6.0.1 (2016-03-04)
- fix init script

## 6.0.0 (2016-03-04)
- update to new API of vpn-ca-api and vpn-server-api
- display the pool a CN is part of
- configuration file is now generated in the user portal
- better error display

## 5.4.0 (2016-02-24)
- switch to Bearer authentication towards backends to improve
  performance (**BREAKING CONFIG**)

## 5.3.2 (2016-02-24)
- no longer need to put `index.php` in the QR code
- add `templateCache` to config example

## 5.3.1 (2016-02-23)
- remove BasicAuthentication for users
- update config template

## 5.3.0 (2016-02-22)
- add "Home" page

## 5.2.3 (2016-02-18)
- also terminate a connection when the user revokes a configuration

## 5.2.2 (2016-02-16)
- fix API URL, for working with SAML it needs to explicity include
  `index.php` in the URL

## 5.2.1 (2016-02-15)
- make init script executable
- fix config template

## 5.2.0 (2016-02-15)
- remove API that was used by vpn-admin-portal as vpn-admin-portal talks to 
  vpn-config-api now
- add new API for consumers that also works when using SAML authentication
- implement a QR code option to enroll using an Android app (Advanced)
- cleanup the UI a lot
- show configurations split up by active, disabled, revoked and expired
- use new vpn-config-api for retrieving configurations
- support Form authentication next to Basic authentication
- implement logout support for Form authentication
- major refactoring of code

## 5.1.2 (2016-02-03)
- update CSS

## 5.1.1 (2016-02-03)
- update iOS documentation

## 5.1.0 (2016-02-03)
- merge the 'Active' and 'Revoked' tab together and have an "Active", 
  "Disabled" and "Revoked" table

## 5.0.8 (2016-01-27)
- update documentation
- remove some redundant text from 'Revoke' tab

## 5.0.7 (2016-01-27)
- add link to OpenVPN Connect for Android FAQ
- add section on Privacy
- update CSS

## 5.0.6 (2016-01-13)
- BUGFIX: strlen($userId_$name) MUST have length <64

## 5.0.5 (2016-01-13)
- sync CSS with vpn-admin-portal
- "No" in confirm revocation is now green

## 5.0.4 (2016-01-13)
- remove boostrap for simple custom CSS

## 5.0.3 (2016-01-09)
- sort the configuration listing descending by `created_at`, showing the newest 
  on top

## 5.0.2 (2016-01-07)
- implement error page when trying to create a configuration with an 
  already existing name
- autofocus the configuration name text box

## 5.0.1 (2016-01-05)
- implement confirmation dialog when revoking a configuration

## 5.0.0 (2016-01-05)
- update to work with new vpn-server-api
- many minor UI improvements
- update documentation section
- implement "whoami" to find out the user ID
- also refresh CRL when the user revokes a configuration
- switch configuration to YAML from ini

## 4.0.0 (2015-12-21)
- completely new UI design
- get rid of `READY` state, see [UPGRADING.md].
- introduce advanced form download, disabled by default, only enabling ZIP 
  download for now

## 3.0.2 (2015-12-17)
- use bootstrap for UI

## 3.0.1 (2015-12-16)
- fix ZIP `key-direction`

## 3.0.0 (2015-12-15)
- no longer store the retrieved configuration in the database, but immediately 
  send it to the client (removed the `config` column from DB, see 
  [UPGRADING.md])
- rename the files in the ZIP to allow for better sorting in directory listings
- store the configuration creation/revocation time (add column `created_at` and
  `revoked_at` to DB, see [UPGRADING.md])
- sort display by status, creation time (no longer alphabetic)
- **NOTE**: all configurations that were created, but not yet downloaded will 
  be unavailable and marked as revoked, see [UPGRADING.md]

## 2.0.1 (2015-12-15)
- when creating a new configuration, immediately redirect to download
  page
- do not use HTTP referer anymore for redirects
- update dependencies

## 2.0.0 (2015-12-11)
- **BREAKING** change the config category `[VpnCertService]` to 
  `[VpnConfigApi]`
- update README
- move classes in new sub folder
- **BREAKING** add ability to block users, requires DB update (add 
  `blocked_users` table)
- **BREAKING** add API calls for integration with `vpn-admin-portal`, 
  configuration file needs to be updated with new `[ApiAuthentication]` 
  section
- remove the `/config/` sub folder, but keep 301 redirect to '/'
- remove the embedded documentation, does not belong here
- add page to show when user is blocked
- cleanup templates
- cleanup authentication

## 1.0.5 (2015-11-29)
- update dependencies

## 1.0.4 (2015-11-16)
- fix file download in IE (issue #7)
- add a new recommended client for Android
- rework and cleanup the templates a bit
- add documentation section

## 1.0.3 (2015-09-29)
- update Service class to support multiple authentication backends by
  default
- **BREAKING** modify configuration file to support multiple authentication 
  backends, needs reconfiguring. See `config/config.ini.example`.
- support both Basic and Mellon authentication now
- add script to generate password hash for BasicAuthentication configuration

## 1.0.2 (2015-08-20)
- update config download page to mention NetworkManager and OpenWrt as examples 
  use cases for the ZIP file
- rename the files in the ZIP export to also include the name of the config to
  allow for example multiple copies in `$HOME/.cert` on Linux

## 1.0.1 (2015-08-10)
- use `fkooman/tpl-twig` instead of included template management

## 1.0.0 (2015-07-20)
- initial release
