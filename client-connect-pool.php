#!/usr/bin/php
<?php

// fill a file with the client IP address at connect time
// depending on its group.

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

// The command is passed the common name and IP address of the  just-authenticated  client as  environmental variables
// The command is also passed the pathname of a freshly created  temporary  file  as  the  last  argument 
if (!isset($argv[1]) || !is_file($argv[1])) {
    echo "FAILED, please launch me as --client-connect\n";
    exit(1);
}

$username=getenv("username");
$ip=getenv("trusted_ip");
openlog("openvpn-manager", LOG_PID, LOG_LOCAL4);

/*
file_put_contents("/var/www/tmp/log",print_r(getenv(),true)."\n",FILE_APPEND);
file_put_contents("/var/www/tmp/log",file_get_contents(getenv("client_connect_deferred_file"))."\n",FILE_APPEND);
file_put_contents("/var/www/tmp/log",file_get_contents(getenv("client_connect_config_file"))."\n",FILE_APPEND);
*/

$stmt = $db->prepare("SELECT * FROM users WHERE username=?;");
$stmt->execute(array($username));
if (!($me=$stmt->fetch())) {
    syslog(LOG_NOTICE, " Login not found user=".$username." ip=".$ip);
    echo "Login not found\n";
    exit(1);
}

// Cherche une IP au hasard, libre, avec verrou, pour cet usager :
$db->exec("LOCK TABLE allocation WRITE;"); 

$stmt=$db->prepare("SELECT * FROM allocation WHERE `group`=? AND user IS NULL ORDER BY RAND() LIMIT 1;");
$stmt->execute([(string)$me["groupname"]]);
if (!($alloc=$stmt->fetch())) {
    syslog(LOG_NOTICE, " No available IP for user=".$username." ip=".$ip." group=".$me["groupname"]);
    echo "No available ip\n";
    $db->exec("UNLOCK TABLES;");
    exit(1);
}
$stmt=$db->prepare("UPDATE allocation SET user=? WHERE ip=?;");
$stmt->execute([$username,$alloc["ip"]]);
$db->exec("UNLOCK TABLES;");

file_put_contents($argv[1],"ifconfig-push ".$alloc["ip"]." ".long2ip(ip2long($alloc["ip"])+1)."\n");

// DNS
if (isset($conf["dnsserver"]) && $conf["dnsserver"]) {
    file_put_contents($argv[1],'push "dhcp-option DNS '.$conf["dnsserver"].'"'."\n",FILE_APPEND);
}

// Log what we're doing.
syslog(LOG_NOTICE, "Connected user=".$username." ip=".$ip." tunnel_ip=".$alloc["ip"]);
closelog();

exit(0);
