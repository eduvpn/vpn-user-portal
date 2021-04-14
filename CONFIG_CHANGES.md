# Configuration Changes

This document describes all configuration file changes since the 2.0.0 release.
This in order to keep track of all changes that were made during the 2.x 
release cycle. 

This will help upgrades to a future 3.x release. Configuration changes during
the 2.x life cycle are NOT required. Any existing configuration file will keep
working!

## 2.3.10

A new configuration boolean has been added to make sessions expire at night 
instead of exactly after `sessionExpiry`. It will make the session expire at 
02:00 for the timezone set in your PHP configuration. You can enable it by 
setting `sessionExpireAtNight` to `true` in the configuration file 
`/etc/vpn-user-portal/config.php`, e.g.:

```
'sessionExpireAtNight' => true,
```

The default is `false` and will keep behavior as before, i.e. expire exactly 
after `sessionExpiry`.

**NOTE**: for now this will only modify the expiry when your session expiry is 
longer than 7 days (`P7D`).

## 2.3.9

We added the translation for Spanish (Latin America). You can add it to 
`config.php` under `supportedLanguages` to enable it in your portal:

```
'es_LA' => 'español',
```

## 2.3.8

The `FormLdapAuthentication` section also takes `searchBindDn` and 
`searchBindPass` options now to allow binding to an LDAP server with an 
account before performing the user's DN search. See 
[LDAP](https://github.com/eduvpn/documentation/blob/v2/LDAP.md) on how to use
it.

## 2.3.7

The `authMethod`, `supportedLanguages` and `sessionExpiry` now have defaults 
when the option is not specified. The `authMethod` default is 
`FormPdoAuthentication`, the `supportedLanguages` default is 
`['en_US' => 'English']` and the `sessionExpiry` default is `P90D`.

## 2.3.5

Under `Api` the `remoteAccessList` is ignored from now on. When `remoteAccess` 
is set to `true` the official eduVPN `server_list.json` is downloaded, 
validated and used to allow access from token issued by the servers listed in 
that file.

The `Api` section is now completely optional. The `consumerList` option under
`Api` is also optional now.

## 2.3.4

We now support `ClientCertAuthentication` as well. It takes no configuration in
`config.php`, see 
[CLIENT_CERT_AUTH](https://github.com/eduvpn/documentation/blob/v2/CLIENT_CERT_AUTH.md) 
for how to set it up, together with web server configuration example.

## 2.3.0

The `SamlAuthentication` module is removed. Use `PhpSamlSpAuthentication` 
instead. See 
[PHP_SAML_SP](https://github.com/eduvpn/documentation/blob/v2/PHP_SAML_SP.md) 
and
[PHP_SAML_SP_UPGRADE](https://github.com/eduvpn/documentation/blob/v2/PHP_SAML_SP_UPGRADE.md)

## 2.2.6

We added support for `array` values of `permissionAttribute` in the 
`FormLdapAuthentication` module. Until now it only took a `string`. The values
of the attributes will be merged and can be used for ACLs or access to the 
portal admin.

## 2.2.5 

We added the translation for Portuguese (Portugal). You can add it to 
`config.php` under `supportedLanguages` to enable it in your portal:

    'pt_PT' => 'Português',

## 2.2.4

You can now set `userIdAttribute` under `FormLdapAuthentication`. The value of
the obtained attribute, instead of the provided "authUser" in the login form 
will be used as the user ID. For example:

    'userIdAttribute' => 'uid',

If not provided, the exact user ID used for binding to the LDAP server will be
used as the user ID in the VPN service.

You can also specify the `addRealm` option that takes a `string` value that 
will add a "realm" to the users specified "authUser". For example, if the user 
provides `foo`, an `addRealm` with value `example.org` would convert the 
"authUser" to `foo@example.org`. If the user specifies `foo@bar.com` and the
`addRealm` value is `example.org` nothing will be changed.

## 2.2.3

We added the translation for German (Germany). You can add it to 
`config.php` under `supportedLanguages` to enable it in your portal:

    'de_DE' => 'Deutsch',

## 2.2.0 

We now support `PhpSamlSpAuthentication` authentication module. It takes all 
the options of `SamlAuthentication`, except `spEntityId`, `idpMetadata`, 
`idpEntityId` and `discoUrl`. See 
[PHP_SAML_SP_UPGRADE](https://github.com/eduvpn/documentation/blob/v2/PHP_SAML_SP_UPGRADE.md)

The use of `SamlAuthentication` is DEPRECATED and `PhpSamlSpAuthentication` is 
STILL not supported!

## 2.1.3

We added the translation for Estonian (Estonia). You can add it to 
`config.php` under `supportedLanguages` to enable it in your portal:

    'et_EE' => 'Eesti',

## 2.1.0

We added the translation for Arabic (Morocco). You can add it to `config.php` 
under `supportedLanguages` to enable it in your portal:

    'ar_MA' => 'العربية',

## 2.0.14

It is now possible to completely reject users from the portal / API by 
requiring them to have a certain permission to get access.

The configuration option `accessPermissionList` takes an array of permissions, 
where the user is allowed access when they have at least one of the permissions
listed. The permissions are taken from the `permissionAttribute` for the 
supporting authentication backends, or from static permissions.

For example:

    'accessPermissionList' => ['administrators', 'employees'],

## 2.0.9

- add `uk_UA` translation. It can be added under `supportedLanguages` as 
  `'uk_UA' => 'Українська'`

## 2.0.8

- due to the update of [php-saml-sp](https://github.com/fkooman/php-saml-sp/) 
  from this version on, also the "friendly" names can be used for the 
  attributes instead of just the `urn:oid` variant with the 
  `SamlAuthentication` plugin. See 
  [this](https://github.com/fkooman/php-saml-sp/blob/7dfda19cfba2d5b84d3b2e99d6e77649cbc8bb7e/src/attribute_mapping.php#L32) 
  file for a mapping

## 2.0.7

- `SamlAuthentication` -> `permissionAttribute` also takes an `array` now, 
  instead of only a string, to allow multiple attributes to be used.
- add `pl_PL` translation. It can be added under `supportedLanguages` as
  `'pl_PL' => 'polski'`
    
## 2.0.4

- Add `MellonAuthentication` -> `nameIdSerialization` (`bool`) and 
  `spEntityId` (`string`) configuration options to serialize 
  `eduPersonTargetedID` to string in the same way the Shibboleth SP does this. 
  In order to use it, the `nameIdSerialization` option has to be set to `true` 
  and the `spEntityId` MUST be the entity ID of the SAML SP as configured in 
  mod_auth_mellon
