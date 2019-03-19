<?php

define("SKIP_IDENTITY_CONTROL",1);
require_once("../head.php");
require_once("header.php");

use OTPHP\TOTP;

unset($_SESSION["username"]);

if (isset($_POST["login"]) && isset($_POST["password"])) {
    $error="";
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?;");
    $stmt->execute(array($_POST["login"]));
    if (!($me=$stmt->fetch())) {
        $error="Login incorrect, please retry";
    }
    if (!$error && !password_verify($_POST["password"],$me["password"])) {
        $error="Login incorrect, please retry";
    }
    if (!$error && $me["usetotp"]) {
        $totp=TOTP::create($me["totp"]);
        if (!$totp->verify($_POST["auth"], null, 1)) {
            $error="Login incorrect, please retry";
        }
    }
    if ($me["isadmin"]!=1) {
        $error="This account is not an administrator. You can't access this interface";
    }
    if (!$error) {
        $_SESSION["username"]=$me["username"];
        header("Location: index.php");
        exit();
    }
}

require_once("message.php");
?>
<div class="row">
    <div class="col-4">
    </div>
        <div class="col-4">
<form method="post">
<?php  csrf_get(); ?>
    <p>Please log into this application</p>
  <div class="form-group">
    <label for="login">Username:</label>
    <input type="text" class="form-control" name="login" id="password" value="<?php if (isset($_POST["login"])) ehe($_POST["login"]); ?>"/>
    <label for="password">Password:</label>
    <input type="password" class="form-control" name="password" id="password" value=""/>
    <label for="auth">Auth token (6 figures):</label>
    <input type="text" class="form-control" name="auth" id="auth" size="10" maxlength="6" value=""/>
   </div>
      <div class="form-group"> 
     <button type="submit" class="btn btn-primary">Enter</button>
    </div>
</form>
</div>
    <div class="col-4">
    </div>
    </div>
    
    <?php
require_once("footer.php");


