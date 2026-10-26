<?php
$root = $ROOT ?? $_SERVER['DOCUMENT_ROOT'];
$home = $HOME ?? $root . '/../..';
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

function pruneMail($email) {
	return str_replace(['\r', '\n'], '', $email);
}

function checkMail($email) {
	$char_list = "a-zA-Z0-9!#$%&'*+\/=?^_{|}~`";

	$test_glob = preg_match("/^[$char_list][$char_list\-\.]*[$char_list]?@[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]?\.[a-zA-Z0-9]{2,63}$/", $email);		// general shape	
	$test_fine = preg_match('/(\.\.|\.@|-@)/', $email);		// refinement

	return ($test_glob && !$test_fine);
}

function sendMail($from = ['arcENSiel' => 'noreply@arcensiel.org'], $to = [], $reply = null, $cc = ['enforce' => false, 'mails' => []], $subject = null, $body = null, $attachments = [], $confidential = false) {
	/*
	retuns {status:status, context:context}

	status 0 - success
	status 1 - failed to send mail
	status 2 - bad email format

	$from: 	[$name => $mail]
	$reply: [$name => $mail]
	$to:	[$mail1, $mail2, ...]
	$cc:	[enforce => bool, mails => []]

	*/
	global $pdo;
	global $home;

	// Check mails validity
	foreach ($from + $to + ($reply ?? []) + $cc['mails'] as $email) {
		if (!checkMail($email)) return ['status' => 2, 'context' => "$email"];
	}
	
	$mail = new PHPMailer(true);

	// Sanitize: convert special characters to html code for safety
	$body_clean = filter_var($body, FILTER_SANITIZE_SPECIAL_CHARS);
	$subject_clean = filter_var($subject, FILTER_SANITIZE_SPECIAL_CHARS);

	try {
		// Config serveur sortant
		$mail->isSMTP();
		$mail->Host = '127.0.0.1';
		$mail->SMTPAuth = true;
		$mail->Port = 25;
		$mail->SMTPAuth = false;
		$mail->SMTPAutoTLS = false;
		$mail->Hostname = "arcensiel.org";
		$mail->CharSet = 'UTF-8';

		// Sender data
		if ($reply) $mail->addReplyTo(array_key_first($reply), array_first($reply));
		$mail->setFrom(array_first($from), array_key_first($from));
		$mail->Sender = 'bounces@arcensiel.org';  
		
		// Receiver data
		foreach ($to as $email) {
			$mail->addAddress($email);
		}

		// CCs
		if (!!$cc['mails']) {
			if ($cc['enforce']) {
				foreach ($cc['mails'] as $mail) {
					$mail->addCC($mail);
				}
			} else {
				$mail->addCustomHeader('Cc', implode(', ', $cc['mails']));
			}
		}		

		// Mail content
		$mail->isHTML(true);
		$mail->Subject = $subject_clean;
		$mail->Body = $body;
		$mail->AltBody = $body_clean;

		// attachments
		foreach ($attachments as $file => $data) {
			$mail->addStringAttachment(base64_decode($data['content']), $file, 'base64', $data['mime']);
		}

		// let's go
		$mail->send();
		$id = $mail->getLastMessageID();
		logger('/log/mail.log', "Email {{$id}} sent from {$mail->From} to [" . implode(',', array_column($mail->getToAddresses(), 0)) . "] with subject '$subject'", 'Send mail');

		// Save mail
		if (!$confidential) {
			$maildata = $mail->getSentMIMEMessage();
			$hash = hash('sha256', $maildata);

			// DB insertion
			$sql = "INSERT INTO mails (mode, sha256, message_id, sent_from, send_to, recipients, cc, subject, sent_at, received_at, text_body, html_body, attachments, size) VALUES(:mode, :sha, :id, :from, :recipient, :to, :cc, :subject, :sent, :received, :text, :html, :attach, CAST(:size AS UNSIGNED));";
			$params = [
						':mode' =>		'sent',
						':sha' =>       $hash,
						':id'  =>       $id,
						':from' =>      json_encode($from, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
						':recipient' => json_encode([(is_numeric(array_key_first($to)) ? $to[array_key_first($to)] : array_key_first($to)) => $to[array_key_first($to)]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
						':to' =>        json_encode($to, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
						':cc' =>        json_encode($cc['mails'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: null,
						':subject' =>   $subject_clean,
						':text' =>      $mail->msgHTML($body),
						':html' =>      $body,
						':size' =>      strlen($maildata),
						':sent' =>      date('Y-m-d H:i:s', time()),
						':received' =>	null,
						':attach' =>    $attachments ?: null
                		];

			try {
				$pdo->prepare($sql)->execute($params);
				logger('/log/mail.log', "Email {{$id}} added to db", 'Insert sent mail in db');
			} catch (PDOException $e) {
				logger('/log/mail.log', "Email {{$id}} could not be added to db: ".json_encode($e), '[ERROR] Failed: Insert sent mail in db');
			}

			// Save on server
			try {
				$dest = $home . '/data/mail/sent/';
				mkdir($dest, 0700, true);
				file_put_contents($dest . strval(time()) . "." . $mail->getSMTPInstance()->getLastTransactionID() . ".domu-teapot", $maildata);
				logger('/log/mail.log', "Email {{$id}} saved on server ($dest)", 'Save sent mail on server');
			} catch (Throwable $e) {
				logger('/log/mail.log', "Email {{$id}} could not be saved: ".json_encode($e), '[ERROR] Failed: Save sent mail on server');
			}
		
		} else {
			logger('/log/mail.log', "Email {{$id}} flagged as confidential: not added to db or saved on server.", 'Skip mail');
		}

		return ['status' => 0, 'context' => null];
	
	} catch(Exception $e) {
		return ['status' => 1, 'context' => $e, 'error' => $mail->ErrorInfo];
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