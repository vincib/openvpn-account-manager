<?php


/* data should be from data.json
   [
   "sessions" => [ [ "id" => <sessionid>, "user" => <sessionuser> ], ... ],
   "traffic" => [ "id" => [ "ts1" => bytes1, "ts2" => bytes2, ... ], ... ],
   ]
   uses the data from the sqlite DB to determine the timeout of each user, or if this user is not concerned.
*/
function kill_timeouted_sessions($data) {
}
