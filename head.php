<?php

require_once("config.php");
require_once("functions.php");
require_once("vendor/autoload.php");

if (!isset($suffix)) $suffix="";

// check we are identified :
try {
    $db = new PDO($conf["db"]["dsn"],$conf["db"]["username"],$conf["db"]["password"]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die ('DB file error, please check application '.print_r($e,true));
}

session_start();
$recvtoken=false;
if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('#bearer\s(.*)#i',$_SERVER['HTTP_AUTHORIZATION'],$mat)) {
   $recvtoken=$mat[1];
}
$istokenadmin=(isset($conf["token"]) && $conf["token"]!==false && $conf["token"]==$recvtoken);
if ($istokenadmin) {
   $_SESSION["username"]="token-admin";
}

if (!defined("SKIP_IDENTITY_CONTROL")) { 
    if (!isset($_SESSION["username"]) && !$istokenadmin) {
        header("Location: login.php");
        exit();
    }
}
