# Changelog

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
