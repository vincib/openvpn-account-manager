<?php

/*
      $db->exec("CREATE TABLE `allocation` (  `ip` varchar(128) NOT NULL,  `user` varchar(512) DEFAULT NULL,  `group` varchar(128) DEFAULT '',  PRIMARY KEY (`ip`),  KEY `group` (`group`,`user`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
*/
for($i=128;$i<=135;$i++) {
    for($j=4;$j<=255;$j+=4) {
        echo "INSERT INTO allocation SET ip='10.5.".$i.".".$j."', user=NULL, `group`='';\n";
    }
}

for($i=136;$i<=143;$i++) {
    for($j=4;$j<=255;$j+=4) {
        echo "INSERT INTO allocation SET ip='10.5.".$i.".".$j."', user=NULL, `group`='OPS';\n";
    }
}

for($i=144;$i<=151;$i++) {
    for($j=4;$j<=255;$j+=4) {
        echo "INSERT INTO allocation SET ip='10.5.".$i.".".$j."', user=NULL, `group`='DEV';\n";
    }
}

for($i=152;$i<=159;$i++) {
    for($j=4;$j<=255;$j+=4) {
        echo "INSERT INTO allocation SET ip='10.5.".$i.".".$j."', user=NULL, `group`='ADMIN';\n";
    }
}
