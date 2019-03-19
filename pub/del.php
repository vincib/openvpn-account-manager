<?php

require_once("../head.php");

if (isset($_GET["username"])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute(array($_GET["username"]));
    $edit=$stmt->fetch();
    if (!$edit) {
        $error="Can't find the required username";
    } else {
        $stmt=$db->prepare("DELETE FROM users WHERE username=?");
        $stmt->execute(array($_GET["username"]));
        $info="User deleted successfully";
    }
}

require_once("index.php");