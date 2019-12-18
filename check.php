#!/usr/bin/php
<?php

// check for a username / password (and 2FA) for openvpn

define("SKIP_IDENTITY_CONTROL",1);
$suffix=".copy"; // use the COPY of the sqlite DB
require_once("head.php");

use OTPHP\TOTP;

$username=getenv("username");
$password=getenv("password");

if (!$username || !$password) {
    echo "FATAL: please use username and password environment variables\n";
    exit(1);
}


// @TODO logs errors on an account ! 
$error="";
$stmt = $db->prepare("SELECT * FROM users WHERE username=?;");
$stmt->execute(array($username));
if (!($me=$stmt->fetch())) {
    echo "Login not found\n";
    exit(1);
}
if ($me["usetotp"]) {
    $auth=substr($password,-6);
    $password=substr($password,0,-6);
    $totp=TOTP::create($me["totp"]);
    if (!$totp->verify($auth, null, 1)) {
        echo "TOTP failure\n";
        exit(1);
    }
}
if (!password_verify($password,$me["password"])) {
    echo "Password incorrect\n";
    exit(1);
}
@file_put_contents($updatespool."/".microtime(true),$username."|".date("Y-m-d H:i:s"));
//$db->exec("UPDATE users SET used=datetime('now') WHERE username='".addslashes($username)."';");
echo "OK\n";
exit(0);

