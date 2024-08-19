<?php

// the socket-client for openvpn.
// keep a permanent connection to MySQL, reconnect if needed
// keep a permanent connection to OpenVPN, reconnect if needed

/*
 * When someone connects, we send the user to the oAuth, and a session is created in oauth_session (status=0)
 * when the user comes back with a successfull session, we allocate an IP address in allocate table, store its oauth_session ID in allocate
 * and mark oauth_session ready (status=1) (this daemon queries it regularly)
CREATE TABLE `oauth_session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned DEFAULT NULL,
  `kid` int(10) unsigned DEFAULT NULL,
  `mtime` datetime DEFAULT current_timestamp(),
  `status` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 

ALTER TABLE allocation ADD oauthid BIGINT UNSIGNED DEFAULT NULL, ADD KEY oauthid (oauthid);

Unknown line:>CLIENT:ADDRESS,0,10.5.150.81,1
Unknown line:>CLIENT:ESTABLISHED,0
Unknown line:>CLIENT:ENV,n_clients=1
...
Unknown line:>CLIENT:ENV,END


*/

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

$ovs=@stream_socket_client("unix:///run/openvpn/server.sock", $error_code,$error_message,10);
if (!$ovs) {
    echo "Can't connect to openvpn, will retry later...\n";
    sleep(2);
    exit();
}

$write=[]; $except=[];
/*
   -1: not checked it is an OpenVPN yet
   0: waiting for events
   1: in status
   2: in client connect 
   3: in client disconnect
   4: just sent a client-pre-auth
   5: just sent a client-auth
*/
$status=-1; 
$data=[];

// This is a state machine that read data on OpenVPN socket and call functions when needed
while (true) {

    // let's read a line on the socket (or timeout at 5sec)
    $read=[$ovs];
    // TODO : replace the select every 2 seconds by a watch file using a inotify socket, that could be selected here too ;) 
    $changed = stream_select($read,$write,$except,2);
    if ($changed==0) {
        nothing_loop();
        continue;
    }
    
    if (feof($ovs)) {
        echo date("Y-m-d H:i:s")." OpenVPN is stopping, will exit\n";
        break;
    }

    // read one line
    $line = stream_get_line($ovs,8192,"\n");
    $line=rtrim($line,"\r"); // we do that because openvpn send \r\n ...
    switch ($status) {

    case -1: // at boottime, we expect one line  
        if (preg_match('#^>INFO:OpenVPN#',$line)) {
            echo "This is an OpenVPN server, fine.\n";
            $status=0;
            break;
        }
        echo "Weird line at boot: $line\n";
        break;

    case 0: // nothing to do and we get a line, let's print it as of now ;)
        if (preg_match('#^>CLIENT:CONNECT,([0-9]+),([0-9]+)$#',$line,$mat)) {
            $status=2; $data=[];
            $cid=$mat[1]; $kid=$mat[2];
            break;
        }
        if (preg_match('#^>CLIENT:DISCONNECT,([0-9]+)$#',$line,$mat)) {
            $status=3; $data=[];
            $cid=$mat[1];
            break;
        }
        if (preg_match('#^>HOLD:Waiting for hold release#',$line)) {
            fputs($ovs,"hold release\n");
            echo "Released held OpenvPN\n";
            $status=4;
            break;
        }

        echo "Unknown line:$line\n";
        break;

    case 1: // getting status. We memorize everything in a hash until we get END. Then call openvpn_status();
        if ($line=="END") {
            $status=0;
            openvpn_status($data);
            $data=[];
        } else {
            $data[]=$line;
        }
        
    case 2: // in client connect env list:
        if ($line==">CLIENT:ENV,END") {
            $status=0;
            openvpn_client_connect($cid,$kid,$data);
            $data=[];
            break;
        }
        if (substr($line,0,12)==">CLIENT:ENV,") {
            list($k,$v)=explode("=",substr($line,12),2);
            $data[$k]=$v;
        }
        break;
        
    case 3: // in client disconnect env list:
        if ($line==">CLIENT:ENV,END") {
            $status=0;
            openvpn_client_disconnect($cid,$data);
            $data=[];
            break;
        }
        if (substr($line,0,12)==">CLIENT:ENV,") {
            list($k,$v)=explode("=",substr($line,12),2);
            $data[$k]=$v;
        }
        break;

    case 4: // sent a pending-auth, or hold release or client-auth
        if (substr($line,0,8)=="SUCCESS:") {
            $status=0;
        }
        if (substr($line,0,6)=="ERROR:") {
            echo date("Y-m-d H:i:s")." WEIRD: $line  after pending/client-auth/hold release\n";
            $status=0;
        }
        break;

    } // switch status

} // main loop 


function openvpn_status($data) {
    global $db;
    // extract the status of openvpn from a list of lines from the unix socket
    $in=""; $fields=[];
    foreach($data as $line) {
        $l=explode(chr(9),$line);
        if ($l[0]=="TIME") echo "Server time is ".date("Y-m-d H:i:s",$l[2])."\n";
        if ($l[0]=="HEADER") {
            $in=$l[1];
            $fields=$l; 
        }
        if ($l[0]==$in) {
            $tmp=[];
            for($i=1;$i<count($l);$i++)
                $tmp[$fields[$i+1]]=$l[$i];
            $status[$in][]=$tmp;
        }
        if ($l[0]=="GLOBAL_STATS") {
            $status[$l[1]]=$l[2];
        }
    } // read all the status lines

    //print_r($status);
    echo date("Y-m-d H:i:s")." OpenVPN status, ".count((isset($status["CLIENT_LIST"]))?$status["CLIENT_LIST"]:[])." clients connected\n";
    /* The following code should never be used: there should be no killed sessions that I don't know about,
     * neither *created* sessions that I don't know about...
     */
    $sids=[];
    if (isset($status["CLIENT_LIST"])) {
        foreach($status["CLIENT_LIST"] as $one) {
            $sids[]=$one["Client ID"];
        }
    }

    $stmt=$db->prepare("SELECT id,cid FROM oauth_session WHERE status=?;");
    $stmt->execute([1]);
    $cids=[];
    while ($c=$stmt->fetch()) {
        $cids[$c["id"]]=$c["cid"];
    }

    // do the bidirectionnal cross-check of sessions between openvpn & my db
    foreach($cids as $id=>$cid) {
        if (!in_array($cid,$sids)) {
            // session destroyed in my absence :/ weird
            $db->exec("DELETE FROM oauth_session WHERE id=".$id.";");
            $db->exec("UPDATE allocation SET user=NULL, oauthid=NULL WHERE oauthid=".$id.";");
        }
    }

    foreach($sids as $id=>$cid) {
        if (!in_array($cid,$cids)) {
            // session created in my absence :/ even weirder 
            echo date("Y-m-d H:i:s")." WEIRD : Client ID $cid created, but I don't know about it :/ \n";
        }
    }
           
}


/** called when a client try to connect.
 * we create a session in oauth_session for this one, (status=0) and send the client to the oauth page.
 */
function openvpn_client_connect($cid,$kid,$data) {
    global $ovs,$status,$db,$conf;
    $stmt=$db->prepare("INSERT INTO oauth_session SET cid=?, kid=?, status=0;");
    $stmt->execute([$cid,$kid]);
    $oauthid = $db->lastInsertId();
    $key=md5($conf["secretstring"].$oauthid.$conf["secretstring"]);
    fputs($ovs,"client-pending-auth $cid $kid WEB_AUTH::".$conf["baseurl"]."oauth2.php?id=".$oauthid."&key=".$key." 180\n"); // you got 3 min to authenticate, seems enough, no?
    $status=4;
    echo date("Y-m-d H:i:s")." openvpn_client_connect [cid:$cid kid:$kid oauthid:$oauthid]\n";
}

function openvpn_client_disconnect($cid,$data) {
    global $db;
    // we purge this session from the DB:
    $stmt = $db->prepare("SELECT id,status FROM oauth_session WHERE cid=?;");
    $stmt->execute([$cid]);
    $found=$stmt->fetch();
    if (!$found) {
        echo date("Y-m-d H:i:s")." WEIRD: can't find destroyed session $cid in the DB\n";
        return;
    }
    $db->exec("DELETE FROM oauth_session WHERE id=".intval($found["id"]).";");
    // this one may update nothing if the session never ended anyway (status=0 or 1)
    $db->exec("UPDATE allocation SET user=NULL, oauthid=NULL WHERE oauthid=".intval($found["id"]).";");
    echo date("Y-m-d H:i:s")." openvpn_client_disconnect [cid:$cid oauthid:".$found["id"]."]\n";
}


function nothing_loop() {
    global $db,$ovs,$status;
    static $lastcheck=0;
    // when nothing happened, maybe we need to do stuff anyway?

//    echo "status:$status\n";

    // at boottime, once we know we have an openvpn, and every 120 sec, (when openvpn doesn't send us anything), we ask openvpn for status:
    if ($lastcheck < (time()-120)) {
        //        echo "getting status\r\n";
        fputs($ovs,"status\n");
        $status=1;
        $data=[];
        $lastcheck=time();
    }
    
    $stmt = $db->prepare("SELECT * FROM oauth_session WHERE status=?;");
    $stmt->execute([1]);
    // we take only one at a time : we expect an answer from openvpn after the client-auth has been sent
    while ($me=$stmt->fetch()) {
        // those session passed from status=0 to status=1, we send information to openvpn about it (OK + IP)
        $db->exec("UPDATE oauth_session SET status=2,mtime=NOW() WHERE id=".intval($me["id"]).";");
        $st2=$db->prepare("SELECT ip FROM allocation WHERE oauthid=?;");
        $st2->execute([intval($me["id"])]);
        $ip=$st2->fetch();
        if (!$ip) {
            echo date("Y-m-d H:i:s")." WEIRD: no IP in allocation for oauthid:".$me["id"]." cid:".$me["cid"]."\n";
        } else {
            fputs($ovs,"client-auth ".$me["cid"]." ".$me["kid"]."\n");
            fputs($ovs,"ifconfig-push ".$ip["ip"]." ".long2ip(ip2long($ip["ip"])+1)."\n");
            // TODO : handle the dns server here (no need for lydia though)
            fputs($ovs,"END\n");
            $status=4;
            break; // went well, we break now, only one at a time
        }
    }
    
}
