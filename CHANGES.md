# Changelog

## 2.0.0 (...)
- **BREAKING** add ability to block users, requires DB update (add 
  `blocked_users` table)
- **BREAKING** add API calls for integration with `vpn-manage-portal`, 
  configuration file needs to be updated with new `[ApiAuthentication]` 
  section
- remove the `/config/` sub folder
- remove the embedded documentation, does not belong here
- add page to show when user is blocked
- cleanup templates

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
