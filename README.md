Openvpn User Manager with 2FA
=============================

This is a php program to manage openvpn accounts (login/password) with 2FA / TOTP management.

License: GPL-v3+
Copyright: 2018-2020 Benjamin Sonntag for Octopuce

Installation
------------

* install a php7.2+ with sqlite3 and imagick (on debian use `apt install php7.2-cli php7.2-fpm php7.2-sqlite3 php7.2-imagick` )
* point an HTTPS vhost of your webserver into pub/ folder (using apache & mod-php or nginx & fpm)
* fill config.php if needed (use config.sample.php as a template, the configuration directory MUST be writable by the PHP Unix user)
* launch `composer update` at the root of the repository to download the dependencies
* launch `php init.php` to initialize an administrator account
* your interface is now available, use the check.php php script into your openvpn configuration to check a username/password
* for openvpn conf, see server.conf, server.up.sh and client.conf sample, use easy-rsa to manage your own pki)
* launch cron-update.php regularly (every 5 minutes minimum recommended) to update the configuration file for OpenVPN (useful for big installations)


