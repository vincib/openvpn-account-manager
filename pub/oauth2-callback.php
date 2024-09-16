<?php 

define("SKIP_IDENTITY_CONTROL",1);
require_once("../head.php");
$ip=$_SERVER["REMOTE_ADDR"];


/* Uncomment this block to skip oauth entirely */
/*
if (isset($_GET["skip"]) && $_GET["skip"]) {
    $email="octopuce";
    $group="";
    $oauthid=$_GET["id"];
    $result = open_session($oauthid,$email,$ip);

    if (!$result) {
        echo "Login not found, please retry or contact your administrator\n";
        exit(1);
    }
    
    // Log what we're doing.
    syslog(LOG_NOTICE, "Connected user=".$username." ip=".$ip." tunnel_ip=".$result);
    closelog();
    echo '<html><head>     <meta name="viewport" content="width=device-width, initial-scale=0.8, shrink-to-fit=no, minimum-scale=0.8, maximum-scale=0.8, user-scalable=no"></head><body>OK, AUTHID '.$oauthid.' IP '.$result.'</body></html>'; 
    exit();
}
*/


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

$email=$ownerDetails->getEmail();
$oauthid=$_SESSION["id"];

$result = open_session($oauthid,$email,$ip);

if (!$result) {
    echo "Login not found, please retry or contact your administrator\n";
    exit(1);
}

$refresh="";
try {
    $refresh= $token->getRefreshToken();
} catch (Exception $e) {
    syslog(LOG_NOTICE, "Exception asking for a refresh token: ".$e->getMessage());
}
if ($refresh) {
    $stmt=$db->prepare("UPDATE oauth_session SET refresh_token=? WHERE id=?;");
    $stmt->execute([$refresh,$oauthid]);
}

// Log what we're doing.
syslog(LOG_NOTICE, "Connected user=".$username." ip=".$ip." tunnel_ip=".$result);
closelog();
?>
<html>
<body>
<?php
echo "You have been successfully connected to OpenVPN, using IP address ".$result." and session ".$oauthid.". You can now close this tab.\n";
?>
<script type="text/javascript">
    window.close();
</script>
</body>
</html>

