<?php

$SERVER_NAME = "host.aurorastation.org";
$SERVER_PORT = 1234;

$CACHE_FILE = "./cache.json";
$MAX_AGE = 60; // in seconds, 1 minute
header("Content-Type: application/json");

// Taken from https://github.com/tgstation/tgstation13.org/blob/master/getserverdata.php#L10
function byond_query($addr, $port, $str) {
	global $error;
	// All queries must begin with a question mark (ie "?players")
	//if($str{0} != '?') $str = ('?' . $str);
	
	/* --- Prepare a packet to send to the server (based on a reverse-engineered packet structure) --- */
	$query = "\x00\x83" . pack('n', strlen($str) + 6) . "\x00\x00\x00\x00\x00" . $str . "\x00";
	
	/* --- Create a socket and connect it to the server --- */
	$server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP) or exit("ERROR");
	socket_set_option($server, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>2, 'usec'=>0));
	socket_set_option($server, SOL_SOCKET, SO_SNDTIMEO, array('sec'=>2, 'usec'=>0));
	if(!socket_connect($server,$addr,$port)) {
		$error = true;
		return "ERROR";
	}
	
	/* --- Send bytes to the server. Loop until all bytes have been sent --- */
	$bytestosend = strlen($query);
	$bytessent = 0;
	while ($bytessent < $bytestosend) {
		//echo $bytessent.'<br>';
		$result = socket_write($server,substr($query,$bytessent),$bytestosend-$bytessent);
		//echo 'Sent '.$result.' bytes<br>';
		if ($result===FALSE) die(socket_strerror(socket_last_error()));
		$bytessent += $result;
	}
	
	/* --- Idle for a while until recieved bytes from game server --- */
	$result = socket_read($server, 10000, PHP_BINARY_READ);
	socket_close($server); // we don't need this anymore
	
	if($result != "") {
		if($result[0] == "\x00" || $result[1] == "\x83") { // make sure it's the right packet format
			
			// Actually begin reading the output:
			$sizebytes = unpack('n', $result[2] . $result[3]); // array size of the type identifier and content
			$size = $sizebytes[1] - 1; // size of the string/floating-point (minus the size of the identifier byte)
			
			if($result[4] == "\x2a") { // 4-byte big-endian floating-point
				$unpackint = unpack('f', $result[5] . $result[6] . $result[7] . $result[8]); // 4 possible bytes: add them up together, unpack them as a floating-point
				return $unpackint[1];
			}
			else if($result[4] == "\x06") { // ASCII string
				$unpackstr = ""; // result string
				$index = 5; // string index
				
				while($size > 0) { // loop through the entire ASCII string
					$size--;
					$unpackstr .= $result[$index]; // add the string position to return string
					$index++;
				}
				return $unpackstr;
			}
		}
	}	
	//if we get to this point, something went wrong;
	$error = true;
	return "ERROR";
}


$req = file_get_contents("php://input");
$decreq = json_decode($req, TRUE);
if($decreq["query"] !== "get_serverstatus" || count($decreq) != 1) {
    http_response_code(400);
	echo json_encode([
        "statuscode" => 400,
        "response" => "Invalid request"
    ]);
	exit();
}

$currentCache = null;
if(file_exists($CACHE_FILE))
{
	$currentCache = json_decode(file_get_contents($CACHE_FILE), TRUE);
}

if($currentCache === null || $currentCache["age"] + $MAX_AGE < time() )
{
	$res = byond_query($SERVER_NAME, $SERVER_PORT, $req);
	if ($res == "ERROR") {
		http_response_code(500);
		echo json_encode([
			"statuscode" => 500,
			"response" => "Server Error"
		]);
	} else {
		// TODO cache $res
		$parsed = json_decode(trim($res), TRUE);
		
		$currentCache = [
			"age" => time(),
			"data" => $parsed
		];
		file_put_contents($CACHE_FILE, json_encode($currentCache));
		http_response_code($parsed["statuscode"]);
		echo trim($res);
	}
} else {
	$parsed = $currentCache["data"];
    http_response_code($parsed["statuscode"]);
    echo json_encode($parsed);
}