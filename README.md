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


easy-rsa setup
--------------

```
apt install easy-rsa
make-cadir /etc/openvpn/pki
cd /etc/openvpn/pki
```

edit vars :

set_var EASYRSA_KEY_SIZE       3072
set_var EASYRSA_CA_EXPIRE      7200
set_var EASYRSA_CERT_EXPIRE    3650

initialize the CA:

* ./easyrsa init-pki
* ./easyrsa build-ca (type a password for your CA, 2 times)
* ./easyrsa gen-dh
* ./easyrsa build-client-full client
* (type a new client password 2 times then the CA password)
* openssl rsa -in pki/private/client.key -out pki/private/client.unprotected.key
* (retype previous client password)
* ./easyrsa build-server-full server
* (type a new server password 2 times then the CA password)
* openssl rsa -in pki/private/server.key -out pki/private/server.unprotected.key
* (retype previous server password)
* openvpn --genkey --secret /etc/openvpn/pki/ta.key 

Now you can fill your openvpn client.ovpn and server.conf configuration files as stated inside them. search for --- or {}




