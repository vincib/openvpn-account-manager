<?php

// this should point to a sqlite3 db initialized by db.openvpn.sql";
$sqlitedb=__DIR__."/db.openvpn.sqlite";
$updatespool="/var/www/vpn/spool";
$touchfile="/var/www/vpn/touchfile";

// if you define "token" here, this allowed people using that token to POST values AS if it was the form, without CSRF need:
// use it via a header: Authorization: Bearer <token>
$token="random32strings ";

// This is the name of your service, as shown to humans in FreeTOTP / GoogleAuth

$vpnname="MyVPN";

// DNS server to push to clients, if any
$dnsserver="";

$timeout_enabled = true;

