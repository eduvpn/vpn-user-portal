# Configuration Changes

This document describes all configuration file changes since the 2.0.0 release.
This in order to keep track of all changes that were made during the 2.x 
release cycle. 

This will help upgrades to a future 3.x release. Configuration changes during
the 2.x life cycle are NOT required. Any existing configuration file will keep
working!

## 2.0.9

- add `uk_UA` translation. It can be added under `supportedLanguages` as 
  `'uk_UA' => 'Українська'`

## 2.0.8

_N/A_

## 2.0.7

- `SamlAuthentication` -> `permissionAttribute` also takes an `array` now, 
  instead of only a string, to allow multiple attributes to be used.
- add `pl_PL` translation. It can be added under `supportedLanguages` as
  `'pl_PL' => 'polski'`

## 2.0.6

_N/A_

## 2.0.5

_N/A_

## 2.0.4

- Add `MellonAuthentication` -> `nameIdSerialization` (`bool`) and 
  `spEntityId` (`string`) configuration options to serialize 
  `eduPersonTargetedID` to string in the same way the Shibboleth SP does this. 
  In order to use it, the `nameIdSerialization` option has to be set to `true` 
  and the `spEntityId` MUST be the entity ID of the SAML SP as configured in 
  mod_auth_mellon

## 2.0.3

_N/A_

## 2.0.2

_N/A_

## 2.0.1

_N/A_
