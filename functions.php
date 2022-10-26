<?php

function cidr_range( $cidr, $chkip=null )
{
    // Assign IP / mask
    list($ip,$mask) = explode("/",$cidr);
    // Sanitize IP
    $ip1 = preg_replace( '_(\d+\.\d+\.\d+\.\d+).*$_', '$1', "$ip.0.0.0" );
    // Calculate range
    $ip2 = long2ip( ip2long( $ip1 ) - 1 + ( 1 << ( 32 - $mask) ) );
    // are we cidr range cheking?
    if ( $chkip != null && ! filter_var( $chkip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false )
        {
            return ip2long( $ip1 ) <= ip2long( $chkip ) && ip2long( $ip2 ) >= ip2long( $chkip ) ? true : false;
        } else {
        return array(ip2long($ip1),ip2long($ip2));
    }
}
/*    
var_dump( cidr_range( "127.0/16", "127.0.0.1" ) );   // bool(true)
var_dump( cidr_range( "127.0/16", "192.168.0.1" ) ); // bool(false)
var_dump( cidr_range( "192.168.0.0/24" ) );          // string(27) "192.168.0.0 - 192.168.0.255"
 */

/** 
 * Allocate IP addresses (divided in /30) to all users.
 * first check that any user that has an IP is in the right CIDR GROUP     
 * then allocate IP address to missing users, including the username specified, or all of them if not specified
 */ 
function allocate_ip($username="") {
    global $db;
    // clean users in no group
    $db->exec("UPDATE users SET ip='' WHERE groupname='';");
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?;");
    $stmt->execute(array($username));
    $edit=$stmt->fetch();
    if ($edit["groupname"]=="") return true; // no group
    // get this group's start / end ip pool :
    $stmt = $db->prepare("SELECT * FROM groups WHERE name=?;");
    $stmt->execute(array($edit["groupname"]));
    $group=$stmt->fetch();
    list($start,$end)=cidr_range($group["cidr"]);
    // Check if the IP is set and in the pool :
    if ($edit["ip"]) {
        $ip = ip2long($edit["ip"]);
        if ($ip>=$start && $ip<=$end) {
            // already good, skipping
            return true;
        }
        echo "group change";
        // ip is set BUT BAD : reset it to null, will check for another one (eg: group change)
        $stmt=$db->prepare("UPDATE users SET ip='' WHERE username=?;");
        $stmt->execute(array($username));
    }
    // IP is empty or incorrect, user need to get one.
    // let's enumerate the other users in that group, and find a free IP    
    $stmt = $db->prepare("SELECT ip FROM users WHERE groupname=? AND ip!='';");
    $stmt->execute(array($edit["groupname"]));
    $start=$start/4; $end=$end/4;
    $pool=array();
    while($other=$stmt->fetch()) {
        echo "O:".$other["ip"]." ";
        // we get a pool from start/4 to end/4 (/30 mode)
        $ipother=ip2long($other["ip"])/4;
        $pool[$ipother]=1;
    }
    print_r($pool);
    $found=false;
    for($i=$start;$i<=$end;$i++) {
        if (!isset($pool[$i]) && ( ($i & 63)!=0 )) { // skip also IPs ending by .0 (for windows, just in case...)
            $found=true;
            $newip=long2ip($i*4);
            break;
        }
    }
    if ($found) {
        $stmt=$db->prepare("UPDATE users SET ip=? WHERE username=?;");
        $stmt->execute(array($newip,$username));
        return true;
    }
    return false;
}


function he($str) {
    return htmlentities($str);
}
function ehe($str) {
    echo htmlentities($str);
}

// TODO : replace by date_my2locale
function date_my2fr($str,$long=false) {
    $plus="";
    if (!$str || $str=="0000-00-00 00:00:00") return "";
    if ($long) $plus=" ".substr($str,11,5);
    return substr($str,8,2)."/".substr($str,5,2)."/".substr($str,0,4).$plus;
}


/** Give a new CSRF uniq token for a form
 * the session must be up since the CSRF is linked
 * to the session cookie. We also need the $db pdo object
 * @return the csrf cookie to add into a csrf hidden field in your form
 */
function csrf_get($return=false) {
    global $db;
    static $token="";
    if (!isset($_SESSION["csrf"])) {
        $_SESSION["csrf"]=md5(mt_rand().mt_rand().mt_rand());
    }
    if ($token=="") {
        $token=md5(mt_rand().mt_rand().mt_rand());
        $new=$db->prepare("INSERT INTO csrf (cookie,token,created) VALUES (?,?,datetime('now'));");
        $new->execute(array($_SESSION["csrf"],$token));
    }
    if ($return)
        return $token;
    echo '<input type="hidden" name="csrf" value="'.$token.'" />';
    return true;
}


/** Check a CSRF token against the current session
 * a token can be only checked once, it's disabled then
 * @param $token string the token to check in the DB + session
 * @return $result integer 0 for invalid token, 1 for good token, -1 for expired token (already used)
 * if a token is invalid or expired, an $msg is raised, that can be displayed
 */
function csrf_check($token=null) {
    global $db,$istokenadmin;

    // skip CSRF check if I'm a bearer token-based admin
    if (isset($istokenadmin) && $istokenadmin) return true;

    if (is_null($token)) $token=$_POST["csrf"];

    if (!isset($_SESSION["csrf"])) {
//        $msg->raise("ERROR", "functions", _("The posted form token is incorrect. Maybe you need to allow cookies"));
        return 0; // no csrf cookie :/
    }
    if (strlen($token)!=32 || strlen($_SESSION["csrf"])!=32) {
        unset($_SESSION["csrf"]);
//        $msg->raise("ERROR", "functions", _("Your cookie or token is invalid"));
        return 0; // invalid csrf cookie
    }
    $get=$db->prepare("SELECT created FROM csrf WHERE cookie=? AND token=?;");
    $get->execute(array($_SESSION["csrf"],$token));
    if (!$get->fetch()) {
//        $msg->raise("ERROR", "functions", _("You can't post twice the same form, please retry."));
        return 0; // invalid csrf cookie
    }
    $db->exec("DELETE FROM csrf WHERE cookie='".addslashes($_SESSION["csrf"])."' AND token='".addslashes($token)."';");
    $db->exec("DELETE FROM csrf WHERE created<datetime('now','1 days');");
    return 1;
}


/**
 * Shows a pager : Previous page 0 1 2 ... 16 17 18 19 20 ... 35 36 37 Next page
 * 
 * Arguments are as follow : 
 * $offset = the current offset from 0 
 * $count = The number of elements shown per page 
 * $total = The total number of elements 
 * $url = The url to show for each page. %%offset%% will be replace by the proper offset
 * $before & $after are HTML code to show before and after the pager **only if the pager is to be shown
 * 
 * @param int $offset
 * @param int $count
 * @param int $total
 * @param string $url
 * @param string $before
 * @param string $after
 * @param boolean $echo
 * @return string
 */
function pager($offset, $count, $total, $url, $before = "", $after = "", $echo = true) {
    $return = "";
    $offset = intval($offset);
    $count = intval($count);
    $total = intval($total);
    if ($offset <= 0) {
        $offset = "0";
    }
    if ($count <= 1) {
        $count = "1";
    }
    if ($total <= 0) {
        $total = "0";
    }
    if ($total < $offset) {
        $offset = max(0, $total - $count);
    }
    if ($total <= $count) { // When there is less element than 1 complete page, just don't do anything :-D
        return true;
    }
    $return .= $before;
    // Shall-we show previous page link ?
    if ($offset) {
        $o = max($offset - $count, 0);
        $return .= "<a href=\"" . str_replace("%%offset%%", $o, $url) . "\" alt=\"(Ctl/Alt-p)\" title=\"(Alt-p)\" accesskey=\"p\">" . _("Previous Page") . "</a> ";
    } else {
        $return .= _("Previous Page") . " ";
    }

    if ($total > (2 * $count)) { // On n'affiche le pager central (0 1 2 ...) s'il y a au moins 2 pages.
        $return .= " - ";
        if (($total < ($count * 10)) && ($total > $count)) {  // moins de 10 pages : 
            for ($i = 0; $i < $total / $count; $i++) {
                $o = $i * $count;
                if ($offset == $o) {
                    $return .= $i . " ";
                } else {
                    $return .= "<a href = \"" . str_replace("%%offset%%", $o, $url) . "\">$i</a> ";
                }
            }
        } else { // Plus de 10 pages, on affiche 0 1 2 , 2 avant et 2 apr�s la page courante, et les 3 dernieres
            for ($i = 0; $i <= 2; $i++) {
                $o = $i * $count;
                if ($offset == $o) {
                    $return .= $i . " ";
                } else {
                    $return .= "<a href=\"" . str_replace("%%offset%%", $o, $url) . "\">$i</a> ";
                }
            }
            if ($offset >= $count && $offset < ($total - 2 * $count)) { // On est entre les milieux ...
                // On affiche 2 avant jusque 2 apr�s l'offset courant mais sans d�border sur les indices affich�s autour
                $start = max(3, intval($offset / $count) - 2);
                $end = min(intval($offset / $count) + 3, intval($total / $count) - 3);
                if ($start != 3) {
                    $return .= " ... ";
                }
                for ($i = $start; $i < $end; $i++) {
                    $o = $i * $count;
                    if ($offset == $o) {
                        $return .= $i . " ";
                    } else {
                        $return .= "<a href=\"" . str_replace("%%offset%%", $o, $url) . "\">$i</a> ";
                    }
                }
                if ($end != intval($total / $count) - 3) {
                    $return .= " ... ";
                }
            } else {
                $return .= " ... ";
            }
            for ($i = intval($total / $count) - 3; $i < $total / $count; $i++) {
                $o = $i * $count;
                if ($offset == $o) {
                    $return .= $i . " ";
                } else {
                    $return .= "<a href=\"" . str_replace("%%offset%%", $o, $url) . "\">$i</a> ";
                }
            }
            $return .= " - ";
        } // More than 10 pages?
    }
    // Shall-we show the next page link ?
    if ($offset + $count < $total) {
        $o = $offset + $count;
        $return .= "<a href=\"" . str_replace("%%offset%%", $o, $url) . "\" alt=\"(Ctl/Alt-s)\" title=\"(Alt-s)\" accesskey=\"s\">" . _("Next Page") . "</a> ";
    } else {
        $return .= _("Next Page") . " ";
    }
    $return .= $after;
    if ($echo) {
        echo $return;
    }
    return $return;
}

