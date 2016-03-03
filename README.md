[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/eduvpn/vpn-user-portal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/eduvpn/vpn-user-portal/?branch=master)

# Introduction

This is the interface for the end user. It provides a portal and an API. 

The authentication mechanisms currently supported are:

* SAML (using Apache mod_mellon)
* Form Authentication (username/password)

# Deployment

See the [documentation](https://github.com/eduvpn/documentation) repository.

# Development

    $ composer install
    $ cp config/config.yaml.example config/config.yaml

Set the `serverMode` to `development` and point `ApiDb/dsn` to a writable
file.
    
    $ mkdir data
    $ php bin/init
    $ php -S localhost:8082 -t web/

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
