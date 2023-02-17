#!/usr/bin/php
<?php

// 2023-01-05 Arnaud Gomes
// Log disconnections.

openlog("client-disconnect", LOG_PID, LOG_LOCAL4);
$username=getenv("username");
$ip=getenv("trusted_ip");
syslog(LOG_NOTICE, "Disconnected user=".$username." ip=".$ip);
closelog();

exit(0);


