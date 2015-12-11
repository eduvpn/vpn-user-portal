[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/eduVPN/vpn-user-portal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/eduVPN/vpn-user-portal/?branch=master)

# Introduction
This project provides a user interface for managing OpenVPN configurations 
using the `vpn-user-portal` software.

It can be used by both users in a browser as well as APIs. The authentication
mechanisms currently supported are:

* SAML (using Apache mod_mellon)
* Basic Authentication

# Production

See the [documentation](https://github.com/eduVPN/documentation) repository.

# Development

## Installation

    $ cd /var/www
    $ sudo mkdir vpn-user-portal
    $ sudo chown fkooman.fkooman vpn-user-portal
    $ git clone https://github.com/eduVPN/vpn-user-portal.git
    $ cd vpn-user-portal
    $ /path/to/composer.phar install
    $ mkdir -p data
    $ sudo chown -R apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/vpn-user-portal/data(/.*)?'
    $ sudo restorecon -R /var/www/vpn-user-portal/data
    $ cp config/config.ini.example config/config.ini

## Configuration
Modify `config/config.ini`.

Now you can run the init script to initialize the DB:

    $ sudo -u apache bin/init

To update the password for API access, or `BasicAuthentication`, use this 
command to generate a new hash:

    $ php -r "require_once 'vendor/autoload.php'; echo password_hash('s3cr3t', PASSWORD_DEFAULT) . PHP_EOL;"

### Apache 

The following configuration can be used in Apache, place it in 
`/etc/httpd/conf.d/vpn-user-portal.conf`:

    Alias /vpn-user-portal /var/www/vpn-user-portal/web

    <Directory /var/www/vpn-user-portal/web>
        AllowOverride none
      
        #Require local 
        Require all granted

        RewriteEngine on
        RewriteBase /vpn-user-portal
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php/$1 [L,QSA]

        # For MellonAuthentication
        # Use the following to test vpn-user-portal without needing to configure
        # mod_mellon.
        #RequestHeader set MELLON-NAME-ID foo

        # For BasicAuthentication
        SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1
    </Directory>

### SAML
See the 
[fkooman/rest-plugin-authentication-mellon](https://github.com/fkooman/php-lib-rest-plugin-authentication-mellon/) 
documentation for more information.
