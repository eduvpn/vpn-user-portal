# Changelog

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
