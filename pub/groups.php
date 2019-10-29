<?php

require_once("../head.php");

require_once("header.php");

require_once("message.php");

?>
<h2>List of Groups:</h2>
<div class="row">
<a href="index.php" class="btn btn-primary">Back to accounts</a>

</div>
    <div class="row">
<table class="tbl">
    <tr><th>Group name</th><th>IP bloc</th><th>Users</th></tr>
<?php

$stmt = $db->prepare("SELECT g.name,g.cidr,count(u.username) AS used FROM groups g LEFT JOIN users u ON u.groupname=g.name GROUP BY g.name;");
$stmt->execute();
while ($res=$stmt->fetch()) {
    echo "<tr><td>".$res["name"]."</td><td>".$res["cidr"]."</td><td>".$res["used"]."</td></tr>\n";
}

?>
</table>

</div>

<?php require_once("footer.php"); ?>
    