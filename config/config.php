<?php
$root = $ROOT ?? $_SERVER['DOCUMENT_ROOT'];
$home = $HOME ?? $root . '/../..';
$serverroot = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'public';

//// VARIABLES
$server_content_file = $root.'/app/pages/backend/content.json';

// MIME
$mimes = [
            'txt'	=> 'text/plain',
			'html'	=> 'text/html',
			'md'	=> 'text/markdown',
			'csv'	=> 'text/csv',
			'css'	=> 'text/css',
            'js'	=> 'text/javascript',
            'svg'	=> 'image/svg+xml',
            'png'	=> 'image/png',
            'jpg'	=> 'image/jpg',
            'jpeg'	=> 'image/jpg',
			'tiff'	=> 'image/tiff',
			'webp'	=> 'image/webp',
			'zip'	=> 'application/zip',
			'7z'	=> 'application/x-7z-compressed',
			'json'	=> 'application/json',
			'pdf'	=> 'application/pdf',
			'sql'	=> 'application/sql',
			'sqlite'=> 'application/vnd.sqlite3',
			'odt'	=> 'application/vnd.oasis.opendocument.text',
			'odp'	=> 'application/vnd.oasis.opendocument.presentation',
			'ods'	=> 'application/vnd.oasis.opendocument.spreadsheet',
			'docx'	=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'pptx'	=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'xlsx'	=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];


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

function truncate($str, $limit = 50) {
	return strlen($str) <= $limit ? $str : substr($str, 0, $limit - 6).' [...]';
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
	global $mimes;
	
	// variables
	$data = [];
	$work_data = scandir($work_path);
	
	// recursive scan
	if ($work_data) {// scan successfull
		$work_data = array_diff($work_data, ['.', '..']);
		foreach($work_data as $filename) {
			$file = $work_path.'/'.$filename;
			$cwd = str_replace($search, $replace, $work_path);
			$url = $cwd.'/'.$filename;
			$ext = end(explode('.', $filename));
			$type = $mimes[$ext] ?? 'application/octet-stream';
			if (is_file($file)) {
				$data[$url] =  [
								"name"		=> $filename,
								"size"		=> filesize($file),
								"type" 		=> $type,
								"created_at"=> date("Y-m-d H:i:s", fileatime($file)),
								"url" 		=> $url,
								"uploader"	=> "UNKNOWN",
								"sha256"	=> hash_file('sha256', $file)
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
	
	/// fetch registered files in database
	$query = $pdo->prepare("SELECT url, name, status, sha256 FROM files;");
	$query->execute();
	$db_files = array_reduce($query->fetchAll(PDO::FETCH_ASSOC), fn ($carry, $item) => [...$carry, $item['sha256'] => $item], []);
	
	/// fetch existing files on server
	$server_files = array_merge(scanner($path, $serverroot), scanner($root.'/app/admin/files', $root.'/app/admin', 'admin://'));

	/// Compare
	$invalid_list 		= []; // files missing from server
	$unregistered_list 	= []; // files on server not in db
	$changed_list 		= []; // content mismatch between db metadata and actual file
	$recovered_list 	= []; // lost files detected on server
	$replaced_list		= []; // lost files detected (filename) but different content
	$duplicate_list		= []; // copy on server of another valid file
	$moved_list			= []; // file with different path between server and db
	
	// Loop through db files
	foreach($db_files as $file) {
		$status = in_array($file['url'], array_keys($server_files));
		
		// file in db not found on server whereas it should
		if (!$status && $file['status']) {
			// work variable to easily find a possible content match
			$server_files_reduced = array_reduce($server_files, fn ($carry, $item) => [...$carry, $item['url'] => $item['sha256']], []);

			// another file with same content found on server
			if (in_array($file['sha256'], array_values($server_files_reduced))) {
				$moved_list[] = ['name' => $file['name'], 'path' => $file['url'], 'new_path' => array_search($file['sha256'], $server_files_reduced), 'sha256' => $file['sha256']];
			
			// no file on server found that match content
			} else {
				$invalid_list[] = ['name' => $file['name'], 'path' => $file['url']];
			}
		
		// file matched on server with same url as current db entry
		} elseif ($status) {
			// get metadata of matched file
			$ref = $server_files[$file['url']];

			// no matching file was expected on server
			if (!$file['status']) {
				// match has same content
				if ($file['sha256'] === $ref['sha256']) {
					$recovered_list[] = ['name' => $file['name'], 'path' => $file['url'], 'sha256' => $file['sha256']];
				
				// match has same url but content differs
				} else {
					$replaced_list[] = ['name' => $file['name'], 'path' => $file['url'], 'current_sha256' => $ref['sha256'], 'saved_sha256' => $file['sha256']];
				}
			
			// a match was expected: check file content integrity
			} elseif ($file['status'] && $file['sha256'] !== $ref['sha256']) {
				$changed_list[] = ['name' => $file['name'], 'path' => $file['url'], 'current_sha256' => $ref['sha256'], 'saved_sha256' => $file['sha256']];
			}
		}
	}

	// Loop through server files
	foreach($server_files as $file) {
		$status = in_array($file['url'], array_map(fn ($item) => $item['url'], $db_files));
		$server_files_reduced = array_reduce($server_files, fn ($carry, $item) => [...$carry, $item['url'] => $item['sha256']], []);
		// file only on server (no matching entry in db)
			// case with matching entry in db already handled
		if (!$status) {
			// content match found with a db entry
			if (in_array($file['sha256'], array_keys($db_files))) {
				$ref = $db_files[$file['sha256']];
				
				// no linked file in: define first matching server file as moved
				if (!$ref['status'] && !in_array($file['sha256'], array_map(fn ($item) => $item['sha256'], $moved_list + $recovered_list))) {
					$moved_list[] = ['name' => $file['name'], 'old_path' => $ref['url'], 'new_path' => $file['url'], 'sha256' => $file['sha256']];
				
				// the match already has its linked file: current file is a duplicate
				} elseif (!in_array($file['url'], array_map(fn ($item) => $item['new_path'], $moved_list))) {
					$duplicate_list[] = ['name' => $file['name'], 'path' => $file['url'], 'original' => $ref['url'], 'sha256' => $file['sha256']];
				}

			// no content match in db: new file
			} else {
				$unregistered_list[] = ['name' => $file['name'], 'path' => $file['url'], 'sha256' => $file['sha256'], 'type' => $file['type']];
			}
		}
	}

	// update status in db
	if ($recovered_list) {
		$sql = "UPDATE files SET status = TRUE WHERE url IN (".implode(',',array_fill(0, count($recovered_list), '?')).");";
		$query = $pdo->prepare($sql)->execute(array_map(fn ($item) => $item['path'], $recovered_list));
		logger('/log/files.log', queryMaker($sql, array_map(fn ($item) => $item['path'], $recovered_list)), 'Update files status');
	}

	if ($invalid_list) {
		$sql = "UPDATE files SET status = FALSE WHERE url IN (".implode(',',array_fill(0, count($invalid_list), '?')).");";
		$query = $pdo->prepare($sql)->execute(array_map(fn ($item) => $item['path'], $invalid_list));
		logger('/log/files.log', queryMaker($sql, array_map(fn ($item) => $item['path'], $invalid_list)), 'Update files status');
	}

	if ($moved_list) {
		$sql = "UPDATE files SET status = FALSE WHERE url IN (".implode(',',array_fill(0, count($moved_list), '?')).");";
		$query = $pdo->prepare($sql)->execute(array_map(fn ($item) => $item['path'], $moved_list));
		logger('/log/files.log', queryMaker($sql, array_map(fn ($item) => $item['path'], $moved_list)), 'Update files status');
	}

	return ['invalid' => $invalid_list, 'unregistered' => $unregistered_list, 'recovered' => $recovered_list, 'changed' => $changed_list, 'replaced' => $replaced_list, 'duplicate' => $duplicate_list, 'moved' => $moved_list];
}

// parse sql queries with parameters
function queryMaker($rawQuery, $parameters) {
	if (preg_match_all('#\?#', $rawQuery, $matches)) {// values bound w/ array -> positionnal
		$query = $rawQuery;
		foreach ($parameters as $value)	{
			$query = preg_replace('#\?#', truncate($value), $query, 1);
		}
	} elseif (preg_match_all('#:[a-zA-Z]+#', $rawQuery, $matches)) {// values bound with parameters
		$query = $rawQuery;
		foreach ($parameters as $name => $value) {
			$query = str_replace($name, truncate($value), $query);
		}
	} else {
		$query = $rawQuery;
	}
	return truncate($query, 250);
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

function sendMail($from = ['arcENSiel' => 'noreply@arcensiel.org'], $to = [], $reply = [], $cc = ['enforce' => false, 'mails' => []], $subject = null, $body = null, $attachments = [], $confidential = false) {
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
	foreach ($from + $to + $reply + $cc['mails'] as $email) {
		if (!checkMail($email)) return ['status' => 2, 'error' => "$email"];
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
		if (!!$reply) $mail->addReplyTo(array_first($reply), array_key_first($reply));
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