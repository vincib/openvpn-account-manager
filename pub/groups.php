<?php

require_once("../head.php");

if ($timeout_enabled && count($_POST)) {
    if (!csrf_check()) {
        $error="The form is incorrect or has been posted twice, please retry";
    }
    if (!$error) {
        foreach($_POST as $k=>$v) {
            if (substr($k,0,15)=="timeoutminutes_") {
                $name=substr($k,15);
                $mb=$_POST["timeouttraffic_".$name];
                $db->exec("UPDATE groups SET timeoutminutes=".intval($v).", timeouttraffic=".intval($mb)." WHERE name='".addslashes($name)."';");
            }
        }
        
    }
}

require_once("header.php");

require_once("message.php");

?>
<h2>List of Groups:</h2>
<div class="row">
<a href="index.php" class="btn btn-primary">Back to accounts</a>

</div>
    <div class="row">
     <form method="post">
<?php
    if ($timeout_enabled) {
        csrf_get();
    }
?>
<table class="tbl">
    <tr><th>Group name</th><th>IP bloc</th><th>Users</th>
<?php if ($timeout_enabled) { ?>
     <th>Timeout <br />in Minutes</th>
         <th>Min traffic<br />for timeout in MB</th>
<?php } ?>
     </tr>
<?php

$stmt = $db->prepare("SELECT g.name,g.cidr,g.cidr6,count(u.username) AS used,g.timeoutminutes,g.timeouttraffic FROM groups g LEFT JOIN users u ON u.groupname=g.name GROUP BY g.name;");
$stmt->execute();
while ($res=$stmt->fetch()) {
    echo "<tr><td>".$res["name"]."</td><td>".$res["cidr"];
    if ($res['cidr6']) echo " &nbsp; ".$res['cidr6'];
    echo "</td><td>".$res["used"]."</td>";
    if ($timeout_enabled) {
        echo "<td><input type=\"text\" size=\"6\" maxlength=\"2\" name=\"timeoutminutes_".$res["name"]."\" id=\"timeoutminutes_".$res["name"]."\" value=\"".intval($res["timeoutminutes"])."\"/></td>";
        echo "<td><input type=\"text\" size=\"6\" maxlength=\"2\" name=\"timeouttraffic_".$res["name"]."\" id=\"timeouttraffic_".$res["name"]."\" value=\"".intval($res["timeouttraffic"])."\"/></td>";
    }
    echo "</tr>\n";
}

?>
</table>
    <?php  if ($timeout_enabled) {
        ?>
        <input type="submit" name="go" class="btn btn-primary" value="Edit the timeout to disconnect clients on this group"/>
        <?php
    }
?>
</form>
    
</div>

<?php require_once("footer.php"); ?>
    
