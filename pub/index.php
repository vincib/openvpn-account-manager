<?php

require_once("../head.php");

require_once("header.php");

$sql="";
$ss="";
if (isset($_GET["s"])) {
    $search=trim(preg_replace("#[^a-z0-9-]#","",strtolower($_GET["s"])));
    if ($search) {
        $sql.=" AND username like '%".addslashes($search)."%'";
        $ss.="&s=".urlencode($search);
    }
}
$count=20;
if (isset($_GET["count"])) {
    $count=intval($_GET["count"]);
    if ($count>1000 || $count<10) $count=20;
}
$offset=0;
if (isset($_GET["offset"])) {
    $offset=intval($_GET["offset"]);
    if ($offset<0) $offset=0;
}

require_once("message.php");

?>
<div class="row">
    <div class="col-6">
    <p>List of your VPN accounts (administrators are in red)</p>
    </div><div class="col-6">
<a href="edit.php" class="btn btn-primary">Create a new account</a>
<a href="groups.php" class="btn btn-secondary">Show groups</a>
<a href="login.php" class="btn btn-secondary">Logout</a>
    </div>
    </div>
    
    <form method="get" action="index.php">
    <div class="row">
        <div class="col-4">
    <input type="text" class="form-control" name="s" value="<?php ehe($search); ?>" placeholder="Search for accounts"/>
    </div><div class="col-2">
    <input type="submit" class="form-control btn btn-primary" value="Search" />
    </div><div class="col-6">
    </div>
    </div>
    </form>
    
        <?php
$stmt = $db->prepare("SELECT count(*) AS total FROM users WHERE 1 $sql;");
$stmt->execute();
$total=$stmt->fetch(); $total=$total["total"];
if ($total<$offset) $offset=0;
pager($offset,$count,$total,"index.php?offset=%%offset%%&count=".$count.$ss,"<div class=\"row\"><div class=\"col-12\">","</div></div>");
?>

    <div class="row">
    <div class="col-12">
  <table class="tbl">
    <tr><th></th><th>Username</th><th>Group</th><th>Created</th><th>Updated</th><th>Used</th><th>2FA?</th><th>IP</th></tr>
<?php
$stmt = $db->prepare("SELECT * FROM users WHERE 1 $sql ORDER BY username LIMIT $offset,$count;");
$stmt->execute();
while ($line=$stmt->fetch()) {
    echo "<tr>";
    echo "<td>";
    echo "<a href=\"edit.php?username=".urlencode($line["username"])."\"><i class=\"fas fa-edit\"></i></a> &nbsp; ";
    echo "<a href=\"del.php?username=".urlencode($line["username"])."\" onclick=\"return confirm('Are you sure you want to delete this account?')\"><i class=\"fas fa-trash-alt\"></i></a> &nbsp; ";
    echo "</td>";
    echo "<td";
    if ($line["isadmin"]) echo " class=\"red\"";
    echo ">".he($line["username"])."</td>";
    echo "<td>".$line["groupname"]."</td>";
    echo "<td>".date_my2fr($line["created"])."</td>";
    echo "<td>".date_my2fr($line["updated"])."</td>";
    echo "<td>".date_my2fr($line["used"])."</td>";
    echo "<td>".(($line["usetotp"])?"Yes":"No")."</td>";
    echo "<td>".$line["ip"];
    if ($line["ipv6"]) echo " ".$line["ipv6"];
    echo "</td>";
    echo "</tr>";
}
?>
</table>
    </div>
    </div>

<?php require_once("footer.php"); ?>
    