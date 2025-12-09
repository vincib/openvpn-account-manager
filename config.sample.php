<?php

$conf=[
	// if you don't use username/password (ex: sqlite mode) you STILL need to define those as NULL
	"db" => [ "dsn" => "mysql:host=localhost;dbname=vpn", "username" => "vpn", "password" => "zei5Ifaixi9eeShahL" ],
	// this is the name of your VPN service. Will be use to qualify accounts when using TOTP
	"vpnname" => "MyVPN",
	// if you define "token" here, this allowed people using that token to POST values AS if it was the form, without CSRF need:
	// use it via a header: Authorization: Bearer <token>
	"token" => "random32strings",
];


// DNS server to push to clients, if any
$dnsserver="";

$timeout_enabled = true;

$timeout_default_minutes = 10; // if a user is not in a group, use these parameters as default 
$timeout_default_traffic = 2;
