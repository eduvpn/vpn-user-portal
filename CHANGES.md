# Changelog

## 3.0.7 (...)
- fix warning message for non-https node URL ([#93](https://todo.sr.ht/~eduvpn/server/93))
- update `nl-NL` translation

## 3.0.6 (2022-09-19)
- [PREVIEW](https://github.com/eduvpn/documentation/blob/v3/PREVIEW_FEATURES.md): 
  implement "Admin API" support ([#16](https://todo.sr.ht/~eduvpn/server/16))
- fix multi node deployments when profile is not installed on all nodes 
  ([#90](https://todo.sr.ht/~eduvpn/server/90))
- simplify `.well-known` handling code in development setup
- add additional `ProfileConfig` tests
- add simple shell script client `dev/api_client.sh` for API testing /
  development

## 3.0.5 (2022-08-15)
- [PREVIEW](https://github.com/eduvpn/documentation/blob/v3/PREVIEW_FEATURES.md): 
  add support for deleting authorization on APIv3 disconnect call 
  ([#78](https://todo.sr.ht/~eduvpn/server/78))

## 3.0.4 (2022-08-03)
- fix handling optional `oListenOn` in multi node setups 
  ([#85](https://todo.sr.ht/~eduvpn/server/85))
- implement `ConnectionHookInterface` to allow for plugins to respond to client
  connect/disconnect events ([#82](https://todo.sr.ht/~eduvpn/server/82))
- re-implement the _syslog_ connection logger on top of 
  `ConnectionHookInterface`
- implement `--list` option for `vpn-user-portal-account` to list user accounts
- [PREVIEW](https://github.com/eduvpn/documentation/blob/v3/PREVIEW_FEATURES.md): 
  add support for running script/command on client connect/disconnect 
  ([#84](https://todo.sr.ht/~eduvpn/server/84))
  
## 3.0.3 (2022-07-27)
- proper logging of authentication failures for local database, LDAP and RADIUS

## 3.0.2 (2022-07-25)
- add Portal URL to manually downloaded configuration file ([#81](https://todo.sr.ht/~eduvpn/server/81))
- update `ar-MA` translation
- require userIdAttribute to be set in LDAP response when requesting it to be
  used ([#83](https://todo.sr.ht/~eduvpn/server/83))

## 3.0.1 (2022-06-08)
- make `oListenOn` accept multiple values 
  ([#75](https://todo.sr.ht/~eduvpn/server/75))
- update `pt-PT` translation
- update `ar-MA` translation

## 3.0.0 (2022-05-18)
- initial 3.x release
