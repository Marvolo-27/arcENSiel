<?php
session_start();
header('Content-Type: application/json');

// extract sent data
$input = json_decode(file_get_contents("php://input"), true);

$action = $_POST['action'] ?? $input ['action'];
$action = $action ?? 'fetch';
$csrf = $_POST['csrf'] ?? $input['csrf'];
$csrf = $csrf ?? null;
$images_types = ['jpg', 'png', 'jpeg', 'tiff', 'webp'];

// Restric to admin user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

if ($_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo json_encode(["error" => "Forbidden: You don't have permission to access this resource."]);
	exit;
}


// authentificate request
if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
	null;
    http_response_code(403);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

// SCAN FILE STATUS
if ($action === 'scan') {
	// assign variables
	$webpath = '/files/uploads';
	$path = $serverroot . $webpath;
	
	/// fetch registered files
	$query = $pdo->prepare("SELECT * from files;");
	$query->execute();
	$registered_data = $query->fetchAll(PDO::FETCH_ASSOC);
	$registered_list = array_map(function ($item){return $item['url'];}, $registered_data);
	
	/// fetch physically existing files
	function scanner($work_path, $search, $replace='') {
		// variables
		global $images_types;
		$data = [];

		// scan cwd
		$work_data = scandir($work_path);
		
		// recursive scan
		if ($work_data) {// scan successfull
			$work_data = array_diff($work_data, ['.', '..']);

			foreach($work_data as $filename) {
				$file = $work_path.'/'.$filename;
				$cwd = str_replace($search, $replace, $work_path);
				$ext = end(array: explode('.', $filename));
				$type = $ext === 'pdf' ? 'application' : (in_array($ext, $images_types) ? 'image':'unlnown');

				if (is_file($file)) {
					$data[] =  [
								"name" => $filename,
								"size" => filesize($file),
								"type" => $type.'/'.$ext,
								"created_at" => date("Y-m-d H:i:s", fileatime($file)),
								"url" => $cwd.'/'.$filename,
								"uploader" => "UNKNOWN",
								"category" => null,
								"subcategory" => null
								];
				} else {
					$data =  array_merge($data, scanner($file, $search, $replace));
				}
			}
		}
		return $data;
	}
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
			$check = true;
			$check = $check && $file['name'] === $ref['name'];
			$check = $check && $file['size'] === $ref['size'];

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

	http_response_code(200);
	$sendback = ['invalid' => $invalid_list, 'unregistered' => $unregistered_list, 'recovered' => $recovered_list, 'changed' => $changed_list];
	echo json_encode($sendback);
	exit;
}

// REMOVE UNREGISTERED FILE FROM SERVER
if ($action === 'delete') {
	$files = $input['files'];

	http_response_code(200);
	$message = ['content', "Files removed successfully."];
	foreach ($files as $file) {
		// handle dmin files
		if (preg_match('#^admin:///#', $file)) {
			$loc = $root."/app/admin";
		} else {
			$loc = $serverroot;
		}

		if(unlink($loc.preg_replace('#^admin://#', '', $file))) {
			logger('/log/files.log', "File '$file' removed from server.", 'Delete file');
		} else {
			logger('/log/files.log', "File '$file' could not be removed from server.", '[ERROR] Failed: delete file');
			http_response_code(500);
			$message = ['error', "File removal did not complete for all files."];
		}
	}

	echo json_encode($message);
	exit;
}

// REMOVE FILE FROM SERVER AND/OR DB
if ($action === 'remove') {
	$url = $input['url'];
	$mode = $input['mode'];
	$status = $input['status'] == 'true';

	// handle dmin files
	if (preg_match('#^admin:///#', $url)) {
		$loc = $root."/app/admin";
	} else {
		$loc = $serverroot;
	}
		
	// delete file if exists
	$deleted = false;
	if ($status) {
		if(unlink($loc.preg_replace('#^admin://#', '', $url))) {
			$deleted = true;
			logger('/log/files.log', "File '$url' removed from server.", 'Delete file');
		} else {
			logger('/log/files.log', "File '$url' could not be removed from server. DB operations cancelled.", '[ERROR] Failed: delete file');
			http_response_code(500);
			echo json_encode(['error', "File '$url' could not be removed from server."]);
			exit;
		}
	}

	// remove entry from db based on user input
	if (!$status || ($deleted && $mode === 'force')) {// file not on server or user choice to remove entry, if deeletion succeeded
		$query = $pdo->prepare("DELETE FROM files WHERE url = :url;");
		$query->execute([':url' => $url]);
		logger('/log/files.log', "DB entry for '$url' deleted.", 'Delete file entry');
	}
	// remove entry from db based on user input
	if ($deleted && $mode === 'keep') {// file not on server or user choice to remove entry, if deeletion succeeded
		$query = $pdo->prepare("UPDATE files SET status = FALSE WHERE url = :url;");
		$query->execute([':url' => $url]);
		logger('/log/files.log', "Status '$url' set to unavailable.", 'Update file status');
	}

	http_response_code(200);
	$message = ['content', "Files removed successfully."];
	echo json_encode($message);
	exit;
}

// DOWNLOAD ADMIN FILE
if ($action === 'encode') {
	ini_set('memory_limit', '512M');
	$raw_url = $input['url'];
	$url = preg_replace('#^admin://#', $root.'/app/admin', $raw_url);

	$source = file_get_contents($url);
	$base64 = base64_encode($source);
	http_response_code(200);
	echo json_encode(['base64' => $base64]);
	exit;
}

// INSERT UNREGISTERED FILES
if ($action === 'insert') {
	$files = $input['files'];

	http_response_code(200);
	$message = ['content', "Files inserted successfully."];
	foreach ($files as $file) {
		// handle dmin files
		if (preg_match('#^admin:///#', $file)) {
			$loc = $root."/app/admin";
		} else {
			$loc = $serverroot;
		}

		$name = end(explode('/', $file));
		$path = $loc.preg_replace('#^admin://#', '', $file);
		$size = filesize($path);
		$ext = end(explode('.', $name));
		$type = $ext === 'pdf' ? 'application/pdf': (in_array($ext, $images_types) ? "image/$ext" : "unknown/$ext");

		$sql = "INSERT INTO files (name, url, used_by, size, description, type, status, uploader, category, subcategory) VALUES (:name, :url, :reference, :size, :desc, :type, TRUE, :user, :cat, :subcat)";
		$params = [
				':name' => $name,
				':url' => $file,
				':reference' => '',
				':size' => $size,
				':desc' => preg_replace("#\.$ext$#", '', $name),
				':type' => $type,
				':user' => $_SESSION['username'],
				':subcat' => 'UNKNOWN',
				':cat' => 'UNKNOWN'
			];
		
		try{
			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', "File '$file' on server inserted in database.".' ; '.PHP_EOL.queryMaker($sql, $params), 'Insert file');
		} catch(PDOException $e) {
			logger('/log/files.log', "File '$file' could not be removed from server.".' ; '.'Source: '.PHP_EOL.queryMaker($sql, $params).' ; '.PHP_EOL.json_encode($e), '[ERROR] Failed: insert file');
			http_response_code(response_code: 500);
			$message = ['error', 'File insertion did not complete for all files.'];
		}
	}

	echo json_encode($message);
	exit;
}

// FETCH FILES OF GIVEN cAT
if ($action === 'fetch') {
	$cat = $input['category'] ?? false;
	
	$sql = $cat ? "SELECT url, name, description from files WHERE category = :cat":"SELECT url, name, size, uploader, type, used_by, description, status, created_at, updated_at from files ORDER BY name ASC";
	$params = $cat ? [':cat' => $cat]:null;
	$query = $pdo->prepare($sql);
	$query->execute($params);
	$output = $query->fetchAll(PDO::FETCH_ASSOC);

	
	
	http_response_code(200);
	$sendback = $output;
	echo json_encode($sendback);
	exit;
	}


// MANAGE REFERENCES FOR GIVEN FILE
if ($action === 'reference') {
	$mode = $input['mode'];
	
	// FETCH USAGE FOR GIVEN FILE
	if ($mode === 'fetch') {
		$url = $input['url'];
		
		$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
		$query->execute([':url' => $url]);
		$output = $query->fetch();

		logger('/log/files.log', "SELECT used_by from files WHERE url = $url", 'Fetch file references');
		
		http_response_code(200);
		$sendback = ['data' => $output];
		echo json_encode($sendback);
		exit;
	}

	// DELETE A REFERENCE FOR GIVEN FILE
	if ($mode === 'delete') {
		$url = $input['url'];
		$usage = $input['usage'];

		$sql = "UPDATE files SET used_by = :usage, updated_at = :time WHERE url = :url;";
		$params = [':url' => $url, ':usage' => $usage, ':time' => date('Y-m-d H:i:s', time())];

		$query = $pdo->prepare($sql);
		$query->execute($params);
		
		logger('/log/files.log', queryMaker($sql, $params), 'Update file references (delete reference)');

		http_response_code(200);
		echo json_encode(['content' => 'Updated successfully']);
		exit;
	}
}

// UPDATE DATA FOR A FILE / UPLOAD NEW FILE
if ($action === 'update') {
	// request extract
	$mode = $_POST['mode'];
	$adminmode = $_POST['admin'] == 'true';
	$target = $_POST['source'] ?? '';
	$description = $_POST['desc'];
	$subcategory = $_POST['subcategory'];
	$src_filename = $_POST['filename'];
	$file_uploaded = $_FILES['file']['error'] !== 4;
	$file_updated = $_POST['file-updated'] == 'true';

	// echo json_encode(['data' => json_encode($_POST), 'file' => json_encode($_FILES), 'test' => $_FILES['file']['error']]);
	
	// Prepare file
	if ($file_uploaded) {
		$file = $_FILES['file']; // array containing file info : name, type, tmp localization, error, size
		$rootpath = ($adminmode ? 'admin://':'').'/files/'.$target;
		$folder = $adminmode ? $root.'/app/admin/files/' : $serverroot."/files/".$target;

			// filename data
		$extension = '.' . strtolower(pathinfo($src_filename, PATHINFO_EXTENSION));		// file extension
		$raw_filename = pathinfo($src_filename, PATHINFO_FILENAME);							// raw filename

		$flat_filename = transliterator_transliterate('Any-Latin; Latin-ASCII', $raw_filename); 					// convert all characters to Latin ASCII alphabet
		$filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $flat_filename); 	// proofed filename w/o extension
		$raw_path = $rootpath . $filename . $extension; // absolute path

		// change to relative path
		$path = str_replace($root, '', $raw_path);
		$path = str_replace("\\", '/', $path);

			// find first unique path for file
		$query = $pdo->prepare("SELECT id from files WHERE url = ?");	// prepare SQL request with placeholder - check user presence
		$query->execute([$path]);

		$count = 1;
		while ($query->fetch()) {
			$raw_path = $rootpath . $filename . "_" . $count . $extension;
			
			$path = str_replace($root, '', $raw_path);
			$path = str_replace("\\", '/', $path); // relative path

			$query = $pdo->prepare("SELECT id from files WHERE url = ?");	// prepare SQL request with placeholder - check user presence
			$query->execute([$path]);

			$count++;
		}

		// stores file in files
		if (move_uploaded_file($file['tmp_name'], str_replace($rootpath, $folder, $raw_path))) {
			logger('/log/files.log', "New file loaded at: $path; $raw_path", 'Upload new file');
			//echo "File stored at: $dest";
		} else {
			http_response_code(500);
			echo json_encode(['error' => "Upload failed for file '".$raw_filename."'".PHP_EOL."Check file size."]);
			logger('/log/files.log', "New file could not be uploaded to $path", 'Failed: upload new file');
			exit;
		}
	} else {
		$path = $_POST['url'];
	}

	// handle db
	try {
		if ($file_uploaded) {// new File
			// standardize mime type
			$type = $file['type'];
			if ($type === 'application/x-zip-compressed') $type = 'application/zip';


			$sql = "INSERT INTO files (name, url, used_by, size, description, type, status, uploader, category, subcategory) VALUES (:name, :url, :reference, :size, :desc, :type, TRUE, :user, 'bilan', :subcat)";
			$params = [
					':name' => str_replace($rootpath, '', $path),
					':url' => $path,
					':reference' => $target ? 'documents/'.$subcategory:'null',
					':size' => $file['size'],
					':type' => $type,
					':desc' => $description,
					':user' => $_SESSION['username'],
					':subcat' => strtoupper($subcategory)
				];

			$query = $pdo->prepare($sql);
			
			foreach ($params as $param => $value) {
				$query->bindValue($param, $value, $param === ':size' ? PDO::PARAM_INT:PDO::PARAM_STR);
			}

			$query->execute();
			logger('/log/files.log', queryMaker($sql, $params), 'Insert file in db');
			
			if ($mode === 'update') {// dereference old file
				$old_url = $_POST['og-url']; // url of file up to now linked to an entry
				
				// fetch current use status
				$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
				$query->execute([':url' => $old_url]);
				$result = $query->fetch();

				$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
				$params = [
					':reference' => preg_replace("#documents/$subcategory,?#", '', $result['used_by']),
					':url' => $old_url,
					':time' => date('Y-m-d H:i:s', time())
					];
				
				$query = $pdo->prepare($sql);
				$query->execute($params);
				logger('/log/files.log', queryMaker($sql, $params), 'Update file references (remove)');
			}
		} else {// existing file
			// update description
			$sql = "UPDATE files SET description = :description, updated_at = :time WHERE url = :url";
			$params = [
					':description' => $description,
					':url' => $path,
					':time' => date('Y-m-d H:i:s', time())
					];

			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', queryMaker($sql, $params), 'Update file description');


			// dereference old file
			if ($file_updated) {
				$old_url = $_POST['og-url']; // url of file up to now linked to an entry
				
				// fetch current use status
				$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
				$query->execute([':url' => $old_url]);
				$result = $query->fetch();

				$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
				$params = [
					':reference' => preg_replace("#documents/$subcategory,?#", '', $result['used_by']),
					':url' => $old_url,
					':time' => date('Y-m-d H:i:s', time())
					];
				
				$query = $pdo->prepare($sql);
				$query->execute($params);
				logger('/log/files.log', queryMaker($sql, $params), 'Update file references (remove document)');
			}

			// reference new file
			if ($file_updated || $mode === 'add') {
					// fetch current use status
				$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
				$query->execute([':url' => $path]);
				$result = $query->fetch();
				$ref = $result['used_by'];
				$comma = $ref ? ',':'';

				$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
				$params = [
							':reference' => $ref.$comma."documents/$subcategory",
							':url' => $path,
							':time' => date('Y-m-d H:i:s', time())
							];

				$query = $pdo->prepare($sql);
				$query->execute($params);
				logger('/log/files.log', queryMaker($sql, $params), 'Update file references (add document)');
			}

			// fetch updated data
			$sql = "SELECT name, size from files WHERE url = :url";
			$query = $pdo->prepare($sql);
			$query->execute([':url' => $path]);
			$file = $query->fetch();
		}
	} catch (PDOException $e) {
			http_response_code(250); // access authorized so success but failed to ouput sql
			echo json_encode( ['code' => 500, 'error' => json_encode($e), 'que'=>$query, 'data'=>$_POST]);
			logger('/log/files.log', 'Source: '.$sql.';'.PHP_EOL.json_encode($e), '[ERROR] Failed: updating file metadata');
			exit;
	}

	$sendback = ['message' => 'done', 'data' => ['path' => $path, 'name' => $src_filename, 'size' => $file['size'], 'desc' => $description], 'source' => $_POST];
	echo json_encode($sendback);
	exit;
}