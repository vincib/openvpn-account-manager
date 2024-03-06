#!/usr/bin/php
<?php

// called when the client is DISconnecting

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

$username=getenv("username");
$tunnel_ip=getenv("ifconfig_pool_remote_ip");
$ip=getenv("trusted_ip");

openlog("openvpn-manager", LOG_PID, LOG_LOCAL4);

$stmt = $db->prepare("SELECT * FROM allocation WHERE ip=?;");
$stmt->execute(array($tunnel_ip));
if (!($me=$stmt->fetch())) {
    syslog(LOG_NOTICE, " IP not found, should not happen ! user=".$username." ip=".$ip);
    exit(0);
}

$stmt=$db->prepare("UPDATE allocation SET user=NULL, `group`='' WHERE ip=?;");
$stmt->execute([$tunnel_ip]);

// Log what we're doing.
syslog(LOG_NOTICE, "Disconnected user=".$username." ip=".$ip." tunnel_ip=".$tunnel_ip);
closelog();

exit(0);
