<?php
session_start();
$root = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . '..';
$serverroot = $_SERVER['DOCUMENT_ROOT'];

//// LIBRARIES
// load libraries
require_once $root . '/libraries/vendor/autoload.php';

//// CREDENTIALS
require_once $root . '/.private/credentials.php';

// Connecting distant db with PDO (php method to use SQL)
$db_server_connection = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];	// make SQL errors throw exceptions


//// FUNCTIONS
// log
function logger($file, $content, $action, $ip = true, $db_access = true) {
	global $root;
	global $ip_address;
	global $ip_access_token;
	global $pdo;
	
	$date = date("Y-m-d H:i:s.v");
	
	// IP Data
	try {
		$client = new IPinfo($ip_access_token);
		$details = $client->getDetails();
	} catch (Exception $e) {$details = false;}

	if ($ip && $details) {
		$ip_data = [
			'city' => $details->city,
			'region' => $details->region,
			'country' => $details->country,
			'tz' => $details->timezone,
			'hostname' => $details->hostname,
			'coords' => $details->loc,
			'ip' => $details->ip,
		];
	} elseif ($ip && !$details) {
		$ip_data = [
			'city' => null,
			'region' => null,
			'country' => null,
			'tz' => null,
			'hostname' => null,
			'coords' => null,
			'ip' => null,
		];
	}

	$user = $_SESSION['username'] ?? '[not connected]';
	$ip_dump = $ip ? "IP : ".$ip_data['ip']." | ".$ip_data['coords'].": ".$ip_data['country'].", ".$ip_data['region'].", ".$ip_data['city'].PHP_EOL."Hostname : ".$ip_data['hostname'].PHP_EOL.PHP_EOL : '';
	$timez = $ip ? ' '.$ip_data['tz'] : '';
	// extract log section
	preg_match('#(?<=/)[a-zA-Z]+(?=\.log)#', $file, $section);

	$logtext = "===========================================================".PHP_EOL.
				"[".
				$date.
				$timez.
				"]".PHP_EOL.
				$ip_dump.
				"User : ".$user.PHP_EOL.
				"Type : ".$action.PHP_EOL.
				"Content : ".$content.PHP_EOL.PHP_EOL;

	file_put_contents($root . $file, $logtext, FILE_APPEND);

	
	if ($db_access) {
		$sql = "INSERT INTO logs (ip, localization, section, message, content, user) VALUES(:ip, :loc, :section, :message, :content, :user);";
		$params =  [
					':ip' => $ip ? ($details ? $ip_data['ip']:'Failed'):'NONE',
					':loc' => $ip ? ($details ? $ip_data['coords'].": ".$ip_data['country'].", ".$ip_data['region'].", ".$ip_data['city']:'Failed'):'NONE',
					':section' => $section[0],
					':message' => $action,
					':content' => $content,
					':user' => $_SESSION['username'] ? $_SESSION['username']:'PUBLIC'
					];
		$query = $pdo->prepare($sql);
		$query->execute($params);
	}

	return null;
}

// parse sql queries with parameters
function queryMaker($rawQuery, $parameters) {
	if (preg_match_all('#\?#', $rawQuery, $matches)) {// values bound w/ array -> positionnal
		$query = $rawQuery;
		foreach ($parameters as $value)	{
			$query = preg_replace('#\?#', $value, $query, 1);
		}
	} elseif (preg_match_all('#:[a-zA-Z]+#', $rawQuery, $matches)) {// values bound with parameters
		$query = $rawQuery;
		foreach ($parameters as $name => $value) {
			$query = str_replace($name, $value, $query);
		}
	} else {
		$query = $rawQuery;
	}
	return $query;
}

// Size format
function formatBytes($bytes, $decimals = 2) {
  if ($bytes === 0) return '0 Byte';
  $k = 1024;
  $sizes = ['Bytes', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
  $i = floor(log($bytes) / log($k));
  return round(($bytes / pow($k, $i)), $decimals).' '.$sizes[$i];
}

// Fetch tags or types
function getTags() {
	global $pdo;
	global $root;

	$query=$pdo->prepare("SELECT id, name, slug FROM tags");
	$query->execute();
	$artTags = $query->fetchAll(PDO::FETCH_ASSOC) ?? null;

	$tagData = [];
	foreach($artTags as $tag) {
		// int id : {name: str name, slug: str slug}
		$tagData[$tag['id']] = ['name' => $tag['name'], 'slug' => $tag['slug']];
	}
	file_put_contents($root . '/.temp/tags.json', json_encode($tagData));

	return $artTags;
}

function getTypes() {
	global $pdo;
	global $root;
	
	$query=$pdo->prepare("SELECT id, name, slug, color FROM types");
	$query->execute();
	$artTypes = $query->fetchAll(PDO::FETCH_ASSOC) ?? null;

	$typeData = [];
	foreach($artTypes as $type) {
		// str id : {name: str name, slug: str slug, color: str hexcode}
		$typeData[strval($type['id'])] = ['slug' => $type['slug'], 'name' => $type['name'], 'color' => $type['color']];
	}
	file_put_contents($root . '/.temp/types.json', json_encode($typeData));

	return $artTypes;
}

//// MAIN
// Connect to SQL db
try {
	$pdo = new PDO($db_server_connection, $user, $pass, $options);
} catch (PDOException $e) {
	http_response_code(250); // access authorized so success but failed to ouput sql
	echo json_encode(['error' => $e]);
	logger('/log/sql.log', json_encode($e), '[ERROR] Failed: establish connection to database', true, false);
	exit;
}

?>