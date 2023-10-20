#!/usr/bin/php
<?php

// update the data.json file containing all connexions from today
// also the timeout.json for all sessions traffic from last 240 minutes. (this allow to kill inactive sessions) 

$verbose=false;
if (isset($argv[1]) && $argv[1]=="verbose") $verbose=true;

define("SKIP_IDENTITY_CONTROL",1);
require_once("head.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// cf https://openvpn.net/community-resources/management-interface/

// se connecte au VPN et lui demande des stats
// génère un rapport quotidien de durée de connexion et de traffic
function mb($i) {
    return intval($i/10485.76)/100;
}

function bytotalonline($a,$b) {
    $key="total_online";
    if ( $a[$key] < $b[$key] ) return 1;
    if ( $a[$key] > $b[$key] ) return -1;
    return 0;
}

$data=json_decode(file_get_contents(__DIR__."/data.json"),true);
if (!$data) $data=["lastts"=>0, "sessions"=>[] ];
$now=time();
// yesterdaymorning
$yesterdaymorning=mktime(0,0,0, date("m",time()-86400), date("d",time()-86400), date("Y",time()-86400) );

// DATA : sessions is a list of sessionid => data (including "in_offset" and "out_offset" which are the bytes in/out at midnight to remove for next day report
// lastts = last time it was updated

$f=fsockopen("localhost","22223",$error,$msg,4);
fputs($f,"status\n");
$clients=[];
while ($s=fgets($f,8192)) {
    if (preg_match('#^CLIENT_LIST	([^	]+)	([^	]+)	[^	]*	[^	]*	([0-9]+)	([0-9]+)	.{24}	([0-9]+)	[^	]+	([0-9]+)	#',$s,$mat)) {
        $ip=preg_replace('#:[0-9]+$#','',$mat[2]);
        $clients[$mat[6]] = [ "ip"=> $ip, "in"=>intval($mat[3]), "out" => intval($mat[4]), "since" => intval($mat[5]), "user" => $mat[1] ];
    }
    
    if (substr($s,0,13)=="ROUTING_TABLE") {
        break;
    }
}

fclose($f);

// now manage the history for the next report.
if ( date("Y-m-d",$data["lastts"]) != date("Y-m-d",$now) ) {
    // new day!
    echo date("Y-m-d",$data["lastts"])." ".date("Y-m-d",$now)."\n" ;
    if (count($data["sessions"])) {
        // send a report 
        // first we create a CSV with all sessions information for the day before

        $details="/tmp/".date("Y-m-d",$now)."-daily-vpn-details.csv";
        $f=fopen($details,"wb");
        fputs($f,"username;start_time;end_time;Mbytes_in;Mbytes_out;ip\n");
        foreach($data["sessions"] as $id=>$c) {
            if (!isset($c["offset_in"])) $c["offset_in"]=0;
            if (!isset($c["offset_out"])) $c["offset_out"]=0;
            fputs($f,$c["user"].";\"".date("d/m/Y H:i:s",$c["since"])."\";");
            if (isset($c["end"])) {
                fputs($f,"\"".date("d/m/Y H:i:s",$c["end"])."\";");
            } else {
                fputs($f,";");
            }
            fputs($f,mb($c["in"]-intval($c["offset_in"])).";");
            fputs($f,mb($c["out"]-intval($c["offset_out"])).";");
            fputs($f,$c["ip"]."\n");
        }
        fclose($f);

        // then we compute a summary PER user 
        // we compute the total bytes IN OUT and the first and last time of login, and the number of vpn sessions
        $users=[];
        foreach($data["sessions"] as $id=>$c) {
            if (!isset($c["offset_in"])) $c["offset_in"]=0;
            if (!isset($c["offset_out"])) $c["offset_out"]=0;

            if (!isset($users[$c["user"]])) {
                // first session of the day for this user:
                $users[$c["user"]]=["start" => max($c["since"],$yesterdaymorning), "end" => (isset($c["end"])?$c["end"]:$now), "in" => $c["in"]-intval($c["offset_in"]), "out" => $c["out"]-intval($c["offset_out"]), "count" => 1, "total_online" => (isset($c["end"])?$c["end"]:$now) - $c["since"] ];
            } else {
                $users[$c["user"]]["start"]=min($users[$c["user"]]["start"],$c["since"]);
                $users[$c["user"]]["end"]=max($users[$c["user"]]["end"], (isset($c["end"])?$c["end"]:$now) );
                $users[$c["user"]]["in"] += $c["in"]-intval($c["offset_in"]);
                $users[$c["user"]]["out"] += $c["out"]-intval($c["offset_out"]);
                $users[$c["user"]]["total_online"] += (isset($c["end"])?$c["end"]:$now) - $c["since"];
                $users[$c["user"]]["count"]++;                
            }
        }

        // tri par durée totale croissante
//        file_put_contents("/tmp/t",serialize($users));
        uasort($users,"bytotalonline");
        
        $summary="/tmp/".date("Y-m-d",$now)."-daily-vpn-summary.csv";
        $f=fopen($summary,"wb");
        fputs($f,"username;start_time;end_time;time_total;total_Mbytes_in;total_Mbytes_out;session_count\n");
        foreach($users as $u=>$c) {
            fputs($f,"\"".$u."\";\"".date("d/m/Y H:i:s",$c["start"])."\";\"".date("d/m/Y H:i:s",$c["end"])."\";\"".date("H:i",$c["total_online"])."\";".mb($c["in"]).";".mb($c["out"]).";".$c["count"]."\n");
        }
        fclose($f);

        // send it by mail : 
        foreach($recipients as $recipient) {
            echo "sending mail to $recipient\n";
            try {
                
                $mail = new PHPMailer(true);
                $mail->isSMTP(); 
                $mail->Host = $smtp["host"];
                $mail->Port = $smtp["port"];
                $mail->SMTPAuth   = true; 
                $mail->Username = $smtp["username"]; // Votre adresse email d'envoi
                $mail->Password = $smtp["password"]; // Le mot de passe de cette adresse email

                $mail->setFrom($from);
                $mail->addAddress($recipient);

                $mail->CharSet = 'UTF-8';
                $mail->isHTML(false);   
                $mail->SMTPAutoTLS = false;
                $mail->Subject = "rapport VPN du ".date("Y-m-d",$now);
                $mail->Body    = "Bonjour,
trouvez ci-joint les 2 rapports quotidiens de l'accès au VPN.

";
                $mail->addAttachment($details);
                $mail->addAttachment($summary);
                $mail->send();
                echo "sent\n";
            } catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
           
        } // send mail to everyone
    }
    // close all sessions
    $data= ["lastts"=>$now, "sessions"=>[] ];
    foreach($clients as $id=>$c) {
        if ($c["user"]=="UNDEF") continue;
        $data["sessions"][$id]=$c;
        $data["sessions"][$id]["offset_in"]=$c["in"];
        $data["sessions"][$id]["offset_out"]=$c["out"];
        $data["sessions"][$id]["since"]=$now; // on commence les sessions à minuit, pas à leur heure de connexion réelle.
    }

} else {
      // not a new day: just complete sessions bytes 
      foreach($clients as $id=>$c) {
          if ($c["user"]=="UNDEF") continue;
          if ( isset($data["sessions"][$id])) {
              // update the session
              $data["sessions"][$id]["in"] = $c["in"];
              $data["sessions"][$id]["out"] = $c["out"];
          } else {
              // new session id, memorise it 
              $data["sessions"][$id]= $c;
          }
      }
      foreach($data["sessions"] as $id=>$c) {
          if ( !isset($clients[$id]) && !isset($data["sessions"][$id]["end"]) ) {
              $data["sessions"][$id]["end"]=$now;
          }
      }
}



$data["lastts"]=$now;

file_put_contents(__DIR__."/data.json",json_encode($data));



