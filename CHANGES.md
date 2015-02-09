# Changelog

## 0.2.8
- update `fkooman/rest` and `fkooman/rest-plugin-mellon` dependencies

## 0.2.7
- fixes for mobile, mobile first with some 'enhancements' for desktop browsers

## 0.2.6
- fix URL rewriting and remove obsolete `portal.php`
- update `fkooman/rest` dependency to 0.6.5

## 0.2.5
- remove the CSS URL configuration again and instead allow for template 
  overrides by copying them from `views/${templateName}` to the configuration 
  directory `config/views/${templateName}`. This allows much more flexibility
  and customization, including the CSS URL
- remove all template configuration as that can now be done by overriding the 
  template
- update dependencies

## 0.2.4
- make CSS URL configurable to allow for style overrides for branding
  **UPDATE CONFIG FILE** by setting `cssUri` in `[Template]` section

## 0.2.3 
- remove duplicate text indicating allowed characters

## 0.2.2
- require the name to be provided when creating a new configuration by 
  enforcing this using HTML5. Mention the allowed characters and also 
  'enforce' this using HTML5.

## 0.2.1
- remove `key-direction 1` line and add 1 to `tls-auth ta.key` line with ZIP
  export to fix NetworkManager import

## 0.2.0
- show a download button that can be used once instead of a directly
  triggered download
- add support for downloading a ZIP file with separate config and
  certificate files, useful for e.g. NetworkManager on Linux
- **BREAKING**: make portal title and first paragraph configurable, this needs
  to be set in the configuration file
- **BREAKING**: make documentation links configurable, these need to be set in
  the config file
- **BREAKING**: move `mellonAttribute` to `Authentication` section, 
  configuration **MUST** be updated
- **BREAKING**: database modified. The `status` column is now an `INTEGER`, 
  added `config` column of type `BLOB` for temporary storing the configuration

## 0.1.12
- validate config name when creating a new config

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
