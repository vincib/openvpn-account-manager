<?php

define("SKIP_IDENTITY_CONTROL",1);
require_once("../head.php");

/* uncomment this to test without real oauth2:*/
// echo '<html><head>     <meta name="viewport" content="width=device-width, initial-scale=0.8, shrink-to-fit=no, minimum-scale=0.8, maximum-scale=0.8, user-scalable=no"></head><body><a href="oauth2-callback.php?skip=1&auth=1&id='.$_GET["id"].'">Click here to auth successfully</a></body></html>'; exit();


use League\OAuth2\Client\Provider\Google;

$provider = new Google($conf["google"]);

if (!isset($_GET["id"]) || !isset($_GET["key"])) {
    echo "ERROR: missing parameters.";
    exit();
}
$id=intval($_GET["id"]);
$key=md5($conf["secretstring"].$id.$conf["secretstring"]);
if ($_GET["key"]!=$key) {
    echo "ERROR: invalid key.";
    exit();    
}

// we store the ID in the session, and start the oAuth2 identification

if (!empty($_GET['error'])) {
    
    // Got an error, probably user denied access
    exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8')." please retry");
    
}


// If we don't have an authorization code then get one
$authUrl = $provider->getAuthorizationUrl();
$_SESSION['oauth2state'] = $provider->getState();
$_SESSION['id'] = $id;
header('Location: ' . $authUrl);
exit;

