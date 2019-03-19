Openvpn User Manager with 2FA
=============================

This is a php program to manage openvpn accounts (login/password) with 2FA / TOTP management.

License: GPL-v3+
Copyright: 2018-2019 Benjamin Sonntag <benjamin@octopuce.fr>

Installation
------------

* install a php7.0+ with sqlite
* point an https vhost of your webserver into pub/ folder (using apache & mod-php or nginx & fpm)
* fill config.php if needed (use config.sample.php as a template)
* launch `php init.php` to initialize an administrator account

