Openvpn User Manager with 2FA
=============================

This is a php program to manage openvpn accounts (login/password) with 2FA / TOTP management.

License: GPL-v3+
Copyright: 2018-2019 Benjamin Sonntag for Octopuce

Installation
------------

* install a php7.0+ with sqlite3 (on debian use `apt install php7.0-cli php7.0-fpm php7.0-sqlite3` )
* point an HTTPS vhost of your webserver into pub/ folder (using apache & mod-php or nginx & fpm)
* fill config.php if needed (use config.sample.php as a template)
* launch `composer update` to download the dependencies
* launch `php init.php` to initialize an administrator account
* your interface is now available, use the check.php php script into your openvpn configuration to check a username/password

add those lines to your openvpn configuration file to check for a username that way :

    auth-user-pass-verify /var/www/openvpn-admin/check.php via-env
    script-security 3 system

