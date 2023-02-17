#!/usr/bin/php
<?php

// fill a file with the client IP address at connect time
// depending on its group.

define("SKIP_IDENTITY_CONTROL",1);
$suffix=".copy"; // use the COPY of the sqlite DB
require_once("head.php");

// The command is passed the common name and IP address of the  just-authenticated  client as  environmental variables
// The command is also passed the pathname of a freshly created  temporary  file  as  the  last  argument 
if (!isset($argv[1]) || !is_file($argv[1])) {
    echo "FAILED, please launch me as --client-connect\n";
    exit(1);
}

$username=getenv("username");

$stmt = $db->prepare("SELECT * FROM users WHERE username=?;");
$stmt->execute(array($username));
if (!($me=$stmt->fetch())) {
    echo "Login not found\n";
    exit(1);
}

if ($me["ip"]=="") exit(0);

file_put_contents($argv[1],"ifconfig-push ".$me["ip"]." ".long2ip(ip2long($me["ip"])+1)."\n");

// Log what we're doing.
openlog("client-connect", LOG_PID, LOG_LOCAL4);
$ip=getenv("trusted_ip");
syslog(LOG_NOTICE, "Connected user=".$username." ip=".$ip);
closelog();

exit(0);


