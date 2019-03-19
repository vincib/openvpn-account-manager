<?php

require_once("config.php");
require_once("functions.php");
require_once("vendor/autoload.php");

// check we are identified :
try {
    $db = new PDO('sqlite:'.$sqlitedb);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die ('DB file error, please check application '.print_r($e,true));
}

session_start();
if (!defined("SKIP_IDENTITY_CONTROL")) { 
    if (!isset($_SESSION["username"])) {
        header("Location: login.php");
        exit();
    }
}
