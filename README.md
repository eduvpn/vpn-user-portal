# Introduction
This project provides a user interface for managing OpenVPN configurations 
using the vpn-cert-service software.

It can be used by both users in a browser as well as APIs. The authentication
mechanisms currently supported are:

* SAML (using Apache mod_mellon)
* Basic Authentication

# Screenshots
![Landing Page](https://raw.githubusercontent.com/eduVPN/vpn-user-portal/master/screenshots/01.png "Landing Page")
![Download Page](https://raw.githubusercontent.com/eduVPN/vpn-user-portal/master/screenshots/02.png "Download Page")

# Installation
It is recommended that you install the software using the RPM package from the
[COPR repository](https://copr.fedoraproject.org/coprs/fkooman/vpn-management/).

The RPM spec files can be found [here](https://github.com/eduVPN/specs).

# Configuration
The software can be configured in `/etc/vpn-user-portal`. After installing the
RPM package an example `config.ini` is placed there. Please modify it as 
required. 

If you want to use the portal from other locations than `localhost`, also 
modify the Apache configuration file in `/etc/httpd.conf/vpn-user-portal.conf`.

## Basic Authentication
If you want to use Basic Authentication you can use the included 
`vpn-user-portal-password-hash` application to generate a hash for your 
secret, e.g.:

    $ vpn-user-portal-password-hash mys3cr3t
    $2y$10$QgAv7FZ4cCUBMHf.gXfLEOa3TezSLUl0rwTYMK2eVqqigK.yMeuwG
    $ 

## SAML
See the 
[fkooman/rest-plugin-authentication-mellon](https://github.com/fkooman/php-lib-rest-plugin-authentication-mellon/) 
documentation for more information. 

# Use
You can use the software in a browser by going to 
[http://localhost/vpn-user-portal/](http://localhost/vpn-user-portal/) and 
authenticating.

# API Use
You can use this software both in a browser, or through an "API". Using "API"
using the SAML authentication is not so useful, so we limit the description
here for Basic authentication.

## Create a configuration
To create a configuration, authenticate with a user and password and specify
the name of the configuration, e.g:

    $ curl -u foo:bar -d 'name=phone' http://localhost/vpn-user-portal/config/

## Retrieve a configuration
To retrieve the configuration:

    $ curl -u foo:bar http://localhost/vpn-user-portal/config/phone/ovpn > phone.ovpn

You can also instead retrieve a ZIP version of the configuration with the 
configuration and certificate/key files seperate:

    $ curl -u foo:bar http://localhost/vpn-user-portal/config/phone/zip > phone.zip

The generated configurations can also be found using the web portal when you
authenticate with the same user.

