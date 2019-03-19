<?php

require_once("../head.php");

use OTPHP\TOTP;

if (!isset($_POST["action"])) {
    echo "ERROR: action not defined";
    exit();
}

switch($_POST["action"]) {
case "2fa":
    $username=preg_replace('#[^0-9a-z-]#','',$_POST["username"]);
    $totp=TOTP::create();
    $totp->setLabel($username.'@'.$vpnname);
    echo $totp->getProvisioningUri();
    break;
}

