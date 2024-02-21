<?php

// initialize the sqlite database with an admin account without 2FA.

$password=substr(md5(mt_rand().mt_rand()),0,12);
define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

try {
    $db->exec("CREATE TABLE users (username text PRIMARY KEY, password text, isadmin boolean default false, totp text, usetotp boolean default false, created datetime, updated datetime, used datetime);");
    $db->exec("CREATE TABLE csrf (cookie char(32) , token char(32), created datetime, primary key (cookie,token));");
} catch (PDOException $e) {
    echo "SQLite already initialized, skipping...\n";
}

// upgrade for group management
try {
    $db->exec("CREATE TABLE groups (name TEXT PRIMARY KEY, cidr TEXT);");
    $db->exec("ALTER TABLE users ADD ip TEXT;");
    $db->exec("ALTER TABLE users ADD groupname TEXT;");
    $db->exec("CREATE INDEX usersgroupname ON users (groupname);"); 
} catch (PDOException $e) {
    echo "Group management already initialized, skipping...\n";
}


// upgrade for group management
try {
    $db->exec("CREATE TABLE `allocation` (  `ip` varchar(128) NOT NULL,  `user` varchar(512) DEFAULT NULL,  `group` varchar(128) DEFAULT '',  PRIMARY KEY (`ip`),  KEY `group` (`group`,`user`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {
    echo "Allocation table already initialized, skipping...\n";
}

$db->query("REPLACE INTO users (username,password,isadmin,totp,usetotp,created,updated,used) VALUES ('admin','".password_hash($password,PASSWORD_DEFAULT)."', 1,'',0,'".date('Y-m-d H:i:s")."','".date('Y-m-d H:i:s")."',null);");
echo "password for admin account is now ".$password."\n";

