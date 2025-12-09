<?php

// launch allocate_ip on all accounts when upgrading to IPv6 managed accounts:
// launch me once (I'm idempotent anyway)

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

$stmt = $db->prepare("SELECT username FROM users;");
$stmt->execute();
while ($one=$stmt->fetch()) {
    echo "allocating ip for ".$one['username']."\n";
    allocate_ip($one['username'],true);
}
