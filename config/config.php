<?php
$root = $ROOT ?? $_SERVER['DOCUMENT_ROOT'];
$serverroot = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'public';

//// VARIABLES
$server_content_file = $root.'/app/pages/backend/content.json';

//// LIBRARIES
// load libraries
require_once $root . '/libraries/vendor/autoload.php';

use ipinfo\ipinfo\IPinfo;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//// CREDENTIALS
require_once $root . '/config/credentials.php';

// Connecting distant db with PDO (php method to use SQL)
$db_server_connection = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];	// make SQL errors throw exceptions


//// FUNCTIONS
/// get password
function decode($name) {
	global $root;

	$mask = file_get_contents($root . '/.private/' . $name . '.key');
	$data = file_get_contents($root . '/.private/' . $name . '.dat');
	$meta = file_get_contents($root . '/.private/' . $name . '.meta');

	$meta = unpack('Voffset/Vlength', $meta);

	$masked_password = substr($data, $meta['offset'], $meta['length']);
	$password = '';

	for ($i=0; $i<strlen($mask); $i++) {
		$password .= $masked_password[$i] ^ $mask[$i];
	}

	return $password;
}

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
	} catch (\Exception $e) {$details = false;}

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
					':user' => $_SESSION['username'] ?: 'PUBLIC'
					];
		$query = $pdo->prepare($sql);
		$query->execute($params);
	}

	return null;
}

function scanner($work_path, $search, $replace='') {
	// variables
	$images_types = ['jpg', 'png', 'jpeg', 'tiff', 'webp'];
	$data = [];
	// scan cwd
	$work_data = scandir($work_path);
	
	// recursive scan
	if ($work_data) {// scan successfull
		$work_data = array_diff($work_data, ['.', '..']);
		foreach($work_data as $filename) {
			$file = $work_path.'/'.$filename;
			$cwd = str_replace($search, $replace, $work_path);
			$ext = end(explode('.', $filename));
			$type = $ext === 'pdf' ? 'application' : (in_array($ext, $images_types) ? 'image':'unknown');
			if (is_file($file)) {
				$data[] =  [
							"name" => $filename,
							"size" => filesize($file),
							"type" => $type.'/'.$ext,
							"created_at" => date("Y-m-d H:i:s", fileatime($file)),
							"url" => $cwd.'/'.$filename,
							"uploader" => "UNKNOWN",
							];
			} else {
				$data =  array_merge($data, scanner($file, $search, $replace));
			}
		}
	}
	return $data;
}

function filesManager() {
	global $pdo;
	global $root;
	global $serverroot;

	// assign variables
	$webpath = '/files/uploads';
	$path = $serverroot . $webpath;
	
	/// fetch registered files
	$query = $pdo->prepare("SELECT * from files;");
	$query->execute();
	$registered_data = $query->fetchAll(PDO::FETCH_ASSOC);
	$registered_list = array_map(function ($item){return $item['url'];}, $registered_data);
	
	/// fetch physically existing files
	$scanned_data = scanner($path, $serverroot);
	$scanned_data = array_merge($scanned_data, scanner($root.'/app/admin/files', $root.'/app/admin', 'admin://'));
	$scanned_list = array_map(function ($item){return $item['url'];}, $scanned_data);

	/// Compare
	// find existing not in registered, registered not in existing (update status = 0), correct files (update status = 1)
	// in fronted, proposer d'ajouter les fichiers unregistered
	$invalid_list = [];
	$unregistered_list = [];
	$recovered_list = [];
	$changed_list = []; // based on file size
	foreach($registered_data as $file) {// Detect recovered and invalid files - based on db
		$status = in_array($file['url'], $scanned_list);
		
		if (!$status && $file['status']) {// file not available but supposed to be
			$invalid_list[] = ['name' => $file['name'], 'path' => $file['url']];

			// update status in db
			$sql = "UPDATE files SET status = FALSE WHERE url = :url;";
			$query = $pdo->prepare($sql);
			$query->execute([':url' => $file['url']]);
			logger('/log/files.log', queryMaker($sql, [':url' => $file['url']]), 'Update file status');
		
		} elseif ($status && !$file['status']) {// unavailable filepath actually scanned
			// extract corresponding filedata of scanned file matching registered file
			foreach ($scanned_data as $scan) {
				if ($scan['url'] === $file['url']) {
					$ref = $scan; // ref is necesserally defined because a match was found
					break;
				}
			}

			// compare metadata
			$check = true && $file['name'] === $ref['name'] && $file['size'] === $ref['size'];

			// conclude
			if ($check) {
				$recovered_list[] = ['name' => $file['name'], 'path' => $file['url']];

				// update status in db
				$sql = "UPDATE files SET status = TRUE WHERE url = :url;";
				$query = $pdo->prepare($sql);
				$query->execute([':url' => $file['url']]);
				logger('/log/files.log', queryMaker($sql, [':url' => $file['url']]), 'Update file status');
			}
		
		} elseif ($status) {
			// extract corresponding filedata of scanned file matching registered file
			foreach ($scanned_data as $scan) {
				if ($scan['url'] === $file['url']) {
					$ref = $scan; // ref is necesserally defined because a match was found
					break;
				}
			}

			// conclude
			if ($file['size'] !== $ref['size']) {// file in db different from file on server
				$changed_list[] = ['name' => $file['name'], 'path' => $file['url']];
			}
		}
	}
	foreach($scanned_data as $file) {// Detect unregistered files - based on scan
		$status = in_array($file['url'], $registered_list);
		$status ? null:$unregistered_list[] = ['name' => $file['name'], 'path' => $file['url']];
	}

	return ['invalid' => $invalid_list, 'unregistered' => $unregistered_list, 'recovered' => $recovered_list, 'changed' => $changed_list];
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
		$typeData[$type['id']] = ['slug' => $type['slug'], 'name' => $type['name'], 'color' => $type['color']];
	}
	file_put_contents($root . '/.temp/types.json', json_encode($typeData));

	return $artTypes;
}

function sendMail($in, $from, $to, $name, $subject, $body, $savedir = null, $reply = null) {
	/*
	retuns {status:status, context:context}

	status 0 - success
	status 1 - failed to send mail
	status 2 - bad email format

	in : 0 = mail sent from arcensiel ; 1 = mail received by arcensiel
	*/

	global $mail_id;
	global $mail_pass;

	$char_list = "a-zA-Z0-9!#$%&'*+\/=?^_{|}~`";
	
	// remove invisible characters
	$from = str_replace(['\r', '\n'], '', $from);
	$to = str_replace(['\r', '\n'], '', $to);

	// analyse email
	$email = $in ? $from:$to;
	$test_glob = preg_match("/^[$char_list][$char_list\-\.]*[$char_list]?@[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]?\.[a-zA-Z0-9]{2,63}$/", $email, $matches_one);		// general shape	
	$test_fine = preg_match('/(\.\.|\.@|-@)/', $email, $matches_two);		// refinement


	// return to contact form
	if (!$test_glob || $test_fine) {// invalid mail
		return ['status' => 2, 'context' => null];

	} else {// valid mail
		$mail = new PHPMailer(true);

		$body_clean = filter_var($body, FILTER_SANITIZE_SPECIAL_CHARS); // convert special characters to html code for safety
		// $body_clean = $body;
		$subject_clean = filter_var($subject, FILTER_SANITIZE_SPECIAL_CHARS);

		try {
			// Config serveur sortant
			$mail->isSMTP();
			$mail->Host = 'smtp.ens-lyon.fr';
			$mail->SMTPAuth = true;
			$mail->Username = $mail_id;
			$mail->Password = $mail_pass;
			$mail->SMTPSecure = 'STARTTLS';
			$mail->Port = 587;
			$mail->CharSet = 'UTF-8';

			// Sender data
			$mail->addReplyTo($email, $name);
			$mail->setFrom($email, $name);

			// Receiver data
			$mail->addAddress($to);

			// Mail content
			$mail->isHTML(true);
			$mail->Subject = $subject_clean;
			$mail->Body = $body;
			$mail->AltBody = $body_clean;

			// let's go
			$mail->send();

			if ($savedir !== null) {// copy sent email to folder
				// copy mail in IMAP folder
				$imapPath = "{imaps.ens-lyon.fr:993/imap/ssl}INBOX/".$savedir;

				$imapStream = imap_open(
					$imapPath,
					$mail_id,
					$mail_pass
				);

				if ($imapStream) {
					imap_append(
						$imapStream,
						$imapPath,
						$mail->getSentMIMEMessage()
					);
					imap_close($imapStream);
				}
			}
			
			return ['status' => 0, 'context' => null];
		
		} catch(Exception $e) {
			return ['status' => 1, 'context' => $e];
		}
	}
}

//// MAIN
// Connect to SQL db
try {
	$pdo = new PDO($db_server_connection, $db_main_user, $db_main_pass, $options);
} catch (PDOException $e) {
	http_response_code(500);
	echo "No connection to database could be established. Access to website impossible.";
	logger('/log/sql.log', json_encode($e), '[ERROR] Failed: establish connection to database', true, false);
	exit;
}

?>