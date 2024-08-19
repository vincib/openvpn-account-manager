<?php

require_once("../head.php");
use OTPHP\TOTP;

require_once("header.php");

$edit=array("username"=>"","password"=>"","usetotp"=>0,"isadmin"=>0);
if (isset($_GET["username"])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute(array($_GET["username"]));
    $edit=$stmt->fetch();
    $edit["id"]=$edit["username"];
}

// ADD OR EDIT ACCOUNT
if (isset($_POST["username"])) {
    $_POST["username"]=trim(strtolower($_POST["username"]));

    $edit["id"]=$_POST["id"];
    $edit["username"]=$_POST["username"];
    $edit["isadmin"]=$_POST["isadmin"];
    $error="";
    
    // check posted data: csrf
    if (!csrf_check()) {
        $error="The form is incorrect or has been posted twice, please retry";
    }

    // username syntax
    if (!$error && !preg_match('#^[0-9a-z\.@-]+$#',$_POST["username"])) {
        $error="The username contains forbidden characters, please verify";
    }

    // edit changing username, or add: check duplicate
    if (!$error && $_POST["username"]!=$_POST["id"]) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute(array($_POST["username"]));
        $already=$stmt->fetch();
        if ($already) {
            $error="Error: this new username already exists!";
        }
    }
    
    // password (if add or if changed while editing account) must be >=8 characters
    if (!$error && ($_POST["id"]=="" || (isset($_POST["password"]) && $_POST["password"])) && strlen($_POST["password"])<8) {
        $error="The password must be at least 8 characters.";
    }

    // did we setup TOTP ?
    $sql="";
    if (!$error && isset($_POST["totp"])) {
        if (preg_match('#\?secret=([0-9A-Z]+)$#',$_POST["totp"],$mat)) {
            $secret=$mat[1];
            $totp=TOTP::create($secret);
            if (!$totp->verify($_POST["totpcheck"], null, 3)) { // large amplitude, because user may have written the password later...
                $error="TOTP value is incorrect, please retry";
            } else {
                $sql.=", totp='".addslashes($secret)."', usetotp=1";
            }
        }
    }
    if (!$error) {
        // EDIT
        if ($_POST["id"]) {
            if (isset($_POST["removetotp"]) && $_POST["removetotp"]) {
                $sql.=", usetotp=0";
            }
            if (isset($_POST["password"]) && $_POST["password"]) {
                $sql.=", password='".addslashes(password_hash($_POST["password"],PASSWORD_DEFAULT))."'";
            }
            $db->exec("UPDATE users SET updated='".date("Y-m-d H:i:s")."', username='".addslashes($_POST["username"])."', groupname='".addslashes($_POST["groupname"])."', isadmin=".intval($_POST["isadmin"])." $sql WHERE username='".addslashes($_POST["id"])."';");
            $info="User account changed successfully";
        } else {
            // ADD 
            $db->exec("INSERT INTO users (username,groupname,password,created,updated,isadmin,usetotp) VALUES ('".addslashes($_POST["username"])."','".addslashes($_POST["groupname"])."','".addslashes(password_hash($_POST["password"],PASSWORD_DEFAULT))."','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."',".intval($_POST["isadmin"]).",0);");
            if ($sql) {
                $db->exec("UPDATE users SET username='".addslashes($_POST["username"])."' $sql WHERE username='".addslashes($_POST["username"])."';");
            }
            $info="User account created successfully";
        }
        // now calculate user's IP address
        allocate_ip($_POST["username"]);
        touch($touchfile);
        require_once("index.php");
        exit();
    }

}

require_once("message.php");
?>
<div class="row">
    <div class="col-12">
    <p><?php if ($edit["id"]) echo "Edit a VPN account"; else echo "Create a new VPN account"; ?></p>
    </div>
    </div>

<form method="post">
<?php csrf_get(); ?>
<input type="hidden" name="id" value="<?php if (isset($edit["id"])) ehe($edit["id"]); ?>">
                   <div class="row">
                   <div class="col-6">
                                                             <label for="username">Username (a-z, 0-9 - allowed)</label>
                   <input type="text" class="form-control" id="username" name="username" value="<?php ehe($edit["username"]); ?>" autocomplete="new-password" required/>
                   </div>
                   <div class="col-6">
                                                             <label for="password">New password (minimum 8 characters)</label>
                   <input type="password" class="form-control" id="password" name="password" value="" autocomplete="new-password" />
                   </div>
                   </div>

 <div class="row">
<div class="col-6">
                                                             <label for="groupname">Group (non mandatory)</label>
                                                             <select id="groupname" name="groupname" class="form-control" ><option value="">-- choose a group --</option>
                                                             <?php
$stmt = $db->prepare("SELECT name FROM groups ORDER BY name;");
$stmt->execute();
while ($res=$stmt->fetch()) {
    echo "<option";
    if ($edit["groupname"]==$res["name"]) echo " selected=\"selected\"";
    echo ">".$res["name"]."</option>\n";
}
                                                             ?>
</select>
</div>
                   </div>

        <div class="row">
        <div class="col-6">
        <input type="checkbox" value="1" id="isadmin" name="isadmin" <?php if ($edit["isadmin"]) echo "checked=\"checked\""; ?>>
        <label for="isadmin">Is it an account administrator?</label>
        </div>
        <div class="col-6">
<?php if ($edit["usetotp"]) {?>
        <input type="checkbox" value="1" id="removetotp" name="removetotp">
                              <label for="removetotp">Disable 2FA on this account (it is currently enabled)</label>
<?php } ?>
        </div>
        </div>

        <div class="row">
<div class="col-6">
                   <input type="hidden" id="totp" name="totp" value=""/>
                                                             <button type="button" class="btn btn-info" onclick="setup2fa()">Setup 2FA on this account</button> <p>(this will overwrite any 2FA currently configured)</p>
                                                             <div id="totpcheckdiv" style="display:none">
                                                             <p>Please scan this QRCode with you FreeOTP or Google Authenticator APP<br />and enter the current 6 digits code to confirm you can use 2FA.</p>
                                                             <input type="text" class="form-control" style="width: 100px" id="totpcheck" name="totpcheck" value="" maxlength="6" autocomplete="new-password" />
                                                             </div>
                                                             </div>
                                                          <div class="col-6" id="div2fa">
                                                             </div>
                                                             </div>

        <div class="row">
                   <div class="col-6">
                   <button type="submit" class="btn btn-success"><?php if ($edit["username"]) echo "Edit"; else echo "Create";?></button>
<button type="button" onclick="document.location='index.php';" class="btn btn-danger">Cancel</button>
                   </div>
        </div>
        
        </form>           

<?php require_once("footer.php"); ?>
    
