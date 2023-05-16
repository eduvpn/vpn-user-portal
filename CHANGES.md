# Changelog

## 3.3.6 (...)
- consider allocated WireGuard IPs for alerting 
  ([#134](https://todo.sr.ht/~eduvpn/server/134))

## 3.3.5 (2023-05-11)
- no longer have a minimum value for `sessionExpiry`, previously it was `PT30M`
- move `StaticPermissionHook` functionality to `UpdateUserInfoHook`
- always fetch static permissions
- consolidate various `Storage::user*` methods
- allow node(s) to specify OpenVPN user/group
  ([#133](https://todo.sr.ht/~eduvpn/server/133))
- fix various static code analysis warnings
- `vpn-user-portal-status` now also shows the number of allocated IP addresses
  for WireGuard (and the number of still free addresses) 
  ([#4](https://todo.sr.ht/~eduvpn/server/4))
- do not show empty array when using `--alert` and `--json` with 
  `vpn-user-portal-status` and there is nothing to alert about

## 3.3.4 (2023-04-25)
- implement support for user specific "Session Expiry" 
  ([#88](https://todo.sr.ht/~eduvpn/server/88))

## 3.3.3 (2023-03-28)
- cleanup VPN protocol selection negotiation 
  ([#128](https://todo.sr.ht/~eduvpn/server/128), 
  [#113](https://todo.sr.ht/~eduvpn/server/113))

## 3.3.2 (2023-03-22)
- make `vpn-user-portal-account --list` also show local users when 
  `DbAuthModule` is used ([#125](https://todo.sr.ht/~eduvpn/server/125))

## 3.3.1 (2023-02-09)
- on "Info" page warn when DNS search domain is not set for a profile, while 
  DNS is provided, but not default gateway 
  ([#120](https://todo.sr.ht/~eduvpn/server/120))
- on "Info" page if DNS is not used in split-tunnel scenario do not warn when 
  DNS traffic is not sent over VPN
- switch to [Argon2id](https://en.wikipedia.org/wiki/Argon2) hashes for 
  local account passwords
- switch to new color palette for "App Usage" on "Stats" page
- show number of users on "Users" page
- expose `created_at` from `Storage::oCertList` and `Storage::wPeerList`
  ([#121](https://todo.sr.ht/~eduvpn/server/121))
- expose the max #available connections per profile on "Connections" page 
  ([#122](https://todo.sr.ht/~eduvpn/server/122))
- make it possible to add additional OAuth API clients 
  ([#119](https://todo.sr.ht/~eduvpn/server/119))
- switch session storage to use JSON instead of PHP serialization
  - this will log everyone out of the portal (if they are currently logged in),
    will NOT affect VPN sessions
- various fixes for issues found by security audit
  - DEC-02-004 WP1: Stored XSS via VPN-configuration display-name (High)
  - DEC-02-006 WP1: Stored XSS via null byte truncation in Radius auth (High)
  - DEC-02-007 WP1: Client disconnection via absent access control (Medium)
  - DEC-02-008 WP1: Bypassing connection threshold with race conditions (Low)
  - DEC-02-001 WP1: Trim function does not HTML-escape short strings (Medium)
  - DEC-02-005 WP3: Unnecessary use of unserialize() for cookie storage (Low)

## 3.3.0 (2023-01-20)
- do not write `syslog` output to `stderr` 
  ([#117](https://todo.sr.ht/~eduvpn/server/117))
- add "#Unique Guest Users" to the last week's "Stats"
- add "#Unique Guest Users" to the "Aggregated Stats"
- "Aggregated Stats" will now contain data starting "yesterday" instead of 
  "one week ago"
- Various database fixes
  - Fix long standing issue with MariaDB/MySQL with "Aggregate Stats" ([#53](https://todo.sr.ht/~eduvpn/server/53))
  - Fix PostgreSQL again with "Aggregate Stats" ([#118](https://todo.sr.ht/~eduvpn/server/118))
  - Add index on `connection_log` table to make generating "Aggregate Stats" 
    fast ([#112](https://todo.sr.ht/~eduvpn/server/112))
  - **NOTE**: a database 
    [migration](https://github.com/eduvpn/documentation/blob/v3/DATABASE.md#database-migration) 
    is necessary. This is done automatically with SQLite. If you switched to 
    using MariaDB/MySQL, or PostgreSQL you MUST do this manually! 

## 3.2.2 (2022-12-22)
- fix for [bug](https://github.com/eduvpn/apple/issues/487) in iOS/macOS app 
  regarding OAuth token refreshing after server upgrade from 2.x to 3.x

## 3.2.1 (2022-12-20)
- fix SQL query for exporting "Aggregate Stats"
- make log of adding/removing peers during sync more informative
- add name of server to aggregate/live stats file downloads

## 3.2.0 (2022-12-16)
- (re)implement tool to generate (reverse) DNS zone files
  ([#25](https://todo.sr.ht/~eduvpn/server/25))
- (re)implement "Static Permissions" for cases where your authentication 
  backend does not (adequately)
  ([#18](https://todo.sr.ht/~eduvpn/server/18)) 
- update for vpn-daemon `/w/remove_peer` changes (v3.0.2)
- add some tests to verify `nodeNumber`, `nodeUrl` and `onNode` profile 
  configuration file
- show `nodeNumber` on Info page for the node(s)
- add `LoggerInterface::debug`
- remove `Tpl::profileIdToDisplayName` "cache"
- refactor connect/disconnect event hooks
- write to `connection_log` table from `ConnectionLogHook`
- make `VPN_PROTO` available on connect/disconnect in `ScriptConnectionHook`
- make `VPN_BYTES_IN` and `VPN_BYTES_OUT` available on disconnect  in 
  `ScriptConnectionHook`
- cleanup "daemon-sync" to make sure the correct connect/disconnect events are
  triggered in all cases
- make "daemon-sync" delete certificates/peers that no longer match the 
  configuration on "apply changes" 
  ([#96](https://todo.sr.ht/~eduvpn/server/96))
- try all nodes when attempting to connect with WireGuard and the first node 
  ran out of free IP addresses ([#110](https://todo.sr.ht/~eduvpn/server/110))
- fix "Aggregate Stats" inefficient `LEFT JOIN` query
  ([#112](https://todo.sr.ht/~eduvpn/server/112))
- sort/group "Aggregate Stats"

## 3.1.7 (2022-11-18)
- fix `ConfigCheck` with DNS template variables 
  ([#107](https://todo.sr.ht/~eduvpn/server/107))
- add network prefix to `AllowedIPs` by default for WireGuard client 
  configuration ([#108](https://todo.sr.ht/~eduvpn/server/108)) 

## 3.1.6 (2022-11-17)
- enforce format of remote user IDs for guest users 
  ([#104](https://todo.sr.ht/~eduvpn/server/104))
- restore `@GW4@` and `@GW6@` template variables for `dnsServerList`
  ([#105](https://todo.sr.ht/~eduvpn/server/105))

## 3.1.5 (2022-11-11)
- fix application stats on "Stats" admin page 
  ([#102](https://todo.sr.ht/~eduvpn/server/102))
- prevent *local* revoked clients from using API in "Guest Usage" scenario 
  ([#103](https://todo.sr.ht/~eduvpn/server/103))

## 3.1.4 (2022-11-08)
- fix OpenVPN special port handling 
  ([#101](https://todo.sr.ht/~eduvpn/server/101))

## 3.1.3 (2022-11-07)
- fix (C) year
- cast `ini_get` return value for `mbstring.func_overload` to bool

## 3.1.2 (2022-11-07)
- make sure `mbstring.func_overload` PHP option is not enabled, show on "Info"
  page if it is
- do proper UTF-8 validation and introduce maximum length of some user provided 
  inputs

## 3.1.1 (2022-11-04)
- verify and trim node keys before allowing them 
  ([#100](https://todo.sr.ht/~eduvpn/server/100))
- fix `nb-NO` translation typo

## 3.1.0 (2022-10-24)
- fix warning message for non-https node URL 
  ([#93](https://todo.sr.ht/~eduvpn/server/93))
- update `nl-NL` translation
- update for `fkooman/oauth2-server` 7.1
- introduce `ApiUserInfo` that wraps the OAuth access token
- enable `iss` query parameter support for OAuth callbacks with 
  `fkooman/oauth2-server` 7.2 ([#91](https://todo.sr.ht/~eduvpn/server/91))
- implement 
  [Guest Access](https://github.com/eduvpn/documentation/blob/v3/GUEST_ACCESS.md) 
  support ([#17](https://todo.sr.ht/~eduvpn/server/17))
  - implement `HmacUserIdHook` to obscure user IDs 
    ([#89](https://todo.sr.ht/~eduvpn/server/89))
  - add [minisign](https://jedisct1.github.io/minisign/) compatible 
    signature verifier
  
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
