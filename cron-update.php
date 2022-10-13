#!/usr/bin/php
<?php

// update the "last connection time" for all users from a spool directory
// and rebuild if necessary all the client-connection settings files

$verbose=false;
if (isset($argv[1]) && $argv[1]=="verbose") $verbose=true;

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

if($d=opendir($updatespool)) {
    while (($c=readdir($d))!==false) {
        if (is_file($updatespool."/".$c)) {
            rename($updatespool."/".$c, $updatespool."/".$c.".tmp");
            list($user,$date)=explode("|",file_get_contents($updatespool."/".$c.".tmp"));
            if ($verbose) echo date("Y-m-d H:i:s")." Updating user $user for date $date \n";
            if ($user && $date) {
                $db->exec("UPDATE users SET used='".addslashes($date)."' WHERE username='".addslashes($user)."';");
            }
            @unlink($updatespool."/".$c.".tmp");
            if ($verbose) echo date("Y-m-d H:i:s")." done \n";
        }
    }
}

// closes the sqlite connection
$db=null;

// if the touchfile saying something changed for a user exists, copy the sqlite DB to the read-file
// prevents a LOCK on the sqlite when openvpn is reading it :)
if (file_exists($touchfile)) {
    if ($verbose) echo date("Y-m-d H:i:s")." managing touchfile \n";
    @unlink($touchfile);
    copy($sqlitedb,$sqlitedb.".tmp");
    rename($sqlitedb.".tmp",$sqlitedb.".copy"); // atomic, used by openvpn
    if ($verbose) echo date("Y-m-d H:i:s")." done \n";
}
