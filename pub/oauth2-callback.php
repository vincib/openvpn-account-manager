<?php

define("SKIP_IDENTITY_CONTROL",1);
require_once("../head.php");

use League\OAuth2\Client\Provider\Google;

$provider = new Google($conf["google"]);

if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    
    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    unset($_SESSION['id']);
    exit('Invalid state, please retry');
    
}

if (!isset($_GET["code"])) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['id']);
    exit('Invalid call, missing code, please retry');
}

// https://vm5.alternc.eu/oauth2-callback.php?state=026c2bb4a2adb827a0ba94a512d0bc0d&code=4%2F0AeaYSHBZJAfL1b_TO_M-ENFgbmc-pAhJ1Ebre6apDWiySOQ3KrJNqUmV-yrwcB7Njt0AxA&scope=email+profile+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.email+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.profile+openid&authuser=1&hd=aaa.octopuce.fr&prompt=none

try {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);
    
    // We got an access token, let's now get the owner details
    $ownerDetails = $provider->getResourceOwner($token);
} catch (Exception $e) {
    // Failed to get user details
    exit('Something went wrong, please retry: ' . $e->getMessage());   
}

/*
// Use these details to create a new profile
printf('name: %s<br>', $ownerDetails->getFirstName());
printf('email: %s<br>', $ownerDetails->getEmail());
*/
list($username,$domain)=explode("@",$ownerDetails->getEmail(),2);
// search for that email in the login list:
// $_SESSION["id"] is the oauth id in the DB, get an IP, mark session as status=1
$oauthid=$_SESSION["id"];
$username2=str_replace(".","",$username);

$stmt = $db->prepare("SELECT * FROM users WHERE username=? OR username=?;");
$stmt->execute(array($username,$username2));
if (!($me=$stmt->fetch())) {
    syslog(LOG_NOTICE, " Login not found user=".$username." or ".$username2." ip=".$ip);
    echo "Login not found, please retry or contact your administrator\n";
    exit(1);
}
$username=$me["username"];

// Cherche une IP au hasard, libre, avec verrou, pour cet usager :
$db->exec("LOCK TABLES allocation WRITE, oauth_session WRITE;"); 

$stmt=$db->prepare("SELECT * FROM allocation WHERE `group`=? AND user IS NULL ORDER BY RAND() LIMIT 1;");
$stmt->execute([(string)$me["groupname"]]);
if (!($alloc=$stmt->fetch())) {
    syslog(LOG_NOTICE, " No available IP for user=".$username." ip=".$ip." group=".$me["groupname"]);
    echo "No available ip, please retry or contact your administrator\n";
    $db->exec("UNLOCK TABLES;");
    exit(1);
}
$stmt=$db->prepare("UPDATE allocation SET user=?, oauthid=? WHERE ip=?;");
$stmt->execute([$username,$oauthid,$alloc["ip"]]);

$stmt=$db->prepare("UPDATE oauth_session SET status=1 WHERE id=?;");
$stmt->execute([$oauthid]);

$db->exec("UNLOCK TABLES;");

//file_put_contents($argv[1],"ifconfig-push ".$alloc["ip"]." ".long2ip(ip2long($alloc["ip"])+1)."\n");
// DNS
//if (isset($conf["dnsserver"]) && $conf["dnsserver"]) {    file_put_contents($argv[1],'push "dhcp-option DNS '.$conf["dnsserver"].'"'."\n",FILE_APPEND);}

// Log what we're doing.
syslog(LOG_NOTICE, "Connected user=".$username." ip=".$alloc["ip"]." tunnel_ip=".$alloc["ip"]);
closelog();

echo "You have been successfully connected to OpenVPN, using IP address ".$alloc["ip"]." You can now close this tab.\n";
exit(0);


