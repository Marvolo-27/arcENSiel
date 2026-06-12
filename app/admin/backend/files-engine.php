<?php
session_start();
header('Content-Type: application/json');

// extract sent data
$input = json_decode(file_get_contents("php://input"), true);

$action = $_POST['action'] ?? $input ['action'] ?? 'fetch';
$csrf = $_POST['csrf'] ?? $input['csrf'] ?? null;
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
	http_response_code(200);
	$sendback = filesManager();
	echo json_encode($sendback);
	exit;
}

// REMOVE UNREGISTERED FILE FROM SERVER
if ($action === 'delete') {
	$files = $input['files'];

	http_response_code(200);
	$sendback = ['message' => "Files removed successfully."];
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
			$sendback = ['error' => "File removal did not complete for all files."];
		}
	}

	echo json_encode($sendback);
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
			echo json_encode(['error' => "File '$url' could not be removed from server."]);
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
	$sendback = ['message' => "Files removed successfully."];
	echo json_encode($sendback);
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
	$sendback = ['message' => "Files inserted successfully."];
	foreach ($files as $file) {
		// handle admin files
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

		$sql = "INSERT INTO files (name, url, size, description, type, status, uploader) VALUES (:name, :url, CAST(:size AS UNSIGNED), :desc, :type, TRUE, :user)";
		$params = [
				':name' => $name,
				':url' => $file,
				':size' => $size,
				':desc' => preg_replace("#\.$ext$#", '', $name),
				':type' => $type,
				':user' => $_SESSION['username'],
			];
		
		try{
			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', "File '$file' on server inserted in database.".' ; '.PHP_EOL.queryMaker($sql, $params), 'Insert file');
		} catch(PDOException $e) {
			logger('/log/files.log', "File '$file' could not be removed from server.".' ; '.'Source: '.PHP_EOL.queryMaker($sql, $params).' ; '.PHP_EOL.json_encode($e), '[ERROR] Failed: insert file');
			http_response_code(response_code: 500);
			$sendback = ['error' => 'File insertion did not complete for all files. Check logs for more details.'];
		}
	}

	echo json_encode($sendback);
	exit;
}

// FETCH FILES OF GIVEN CAT
if ($action === 'fetch') {
	// Update files status
	filesManager();

	// Extract data
	$category = (array) $input['category'] ?? false;
	$category_sql = $category ? ' AND categories.slug IN (:'.implode(',:', $category).')' : null;
	$params = $category ? array_reduce($category, function ($carry, $item) {$carry[':'.$item] = $item; return $carry;}, []) : null;

	$filetype = (array) $input['filetype'] ?? false;
	$filetype_sql = $filetype ? preg_replace(['#/#','#-#'], '_', ' AND (LOCATE(:'.implode(', type) > 0 OR LOCATE(:', $filetype).', type) > 0)') : null;
	$params = $filetype ? array_merge((array) $params, array_reduce($filetype, function ($carry, $item) {$carry[':'.preg_replace(['#-#','#/#'], '_', $item)] = $item; return $carry;}, [])) : $params;
	
	$sql = ($category || $filetype) ?
	"SELECT url, files.name AS name, description, GROUP_CONCAT(categories.slug SEPARATOR ',') AS categories
	FROM files
		LEFT JOIN files_categories ON files.id = file_id
		LEFT JOIN categories ON categories.id = category_id
	WHERE LOCATE('admin:///', files.url) = 0 ".($category ? $category_sql : '').($filetype ? $filetype_sql : '').
	" GROUP BY files.id"
	:
	"WITH
		references_list AS (
			SELECT file_id, CONCAT('article/', slug) AS reference, NULL AS category
			FROM articles_covers
				JOIN articles ON article_id = articles.id
			UNION ALL
			SELECT file_id, CONCAT(sections.slug, '/', categories.slug) AS reference, categories.slug AS category
			FROM files_categories
				JOIN categories ON category_id = categories.id
				JOIN sections ON section_id = sections.id)
	SELECT url, name, size, uploader, type, description, status, created_at, updated_at, COALESCE(GROUP_CONCAT(reference SEPARATOR ', '), '') AS usages, GROUP_CONCAT(category SEPARATOR ',') AS categories
	FROM files
		LEFT JOIN references_list ON files.id = file_id
	GROUP BY files.id
	ORDER BY name ASC;";

	$query = $pdo->prepare($sql);
	$query->execute($params);
	$output = $query->fetchAll(PDO::FETCH_ASSOC);
	
	// logger('/log/files.log', 'Source: '.PHP_EOL.queryMaker($sql, $params), 'Fetch files');

	http_response_code(200);
	echo json_encode($output);
	exit;
	}


// MANAGE REFERENCES FOR GIVEN FILE
if ($action === 'reference') {
	$mode = $input['mode'];
	
	// DELETE A REFERENCE FOR GIVEN FILE
	if ($mode === 'delete') {
		$url = $input['url'];
		$category = $input['category'];

		$sql = "DELETE files_categories FROM files_categories JOIN files ON file_id = files.id JOIN categories ON category_id = categories.id WHERE url = :url AND slug = :slug";
		$params = [
				':url' => $url,
				':slug' => $category
				];
		$query = $pdo->prepare($sql);
		$query->execute($params);
		
		logger('/log/files.log', queryMaker($sql, $params), 'Update file references');
		http_response_code(200);
		echo json_encode(['message' => 'Reference updated successfully']);
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
	$section = $_POST['section'];
	$category = $_POST['category'];
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


			$sql = "INSERT INTO files (name, url, size, description, type, status, uploader) VALUES (:name, :url, CAST(:size AS UNSIGNED), :desc, :type, TRUE, :user)";
			$params = [
					':name' => str_replace($rootpath, '', $path),
					':url' => $path,
					':size' => $file['size'],
					':type' => $type,
					':desc' => $description,
					':user' => $_SESSION['username'],
				];

			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', queryMaker($sql, $params), 'Insert file in db');
		
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
		}
		
		// dereference old file
		if ($file_updated) {
			$old_url = $_POST['og-url']; // url of file up to now linked to an entry
			
			// fetch current use status
			$sql = "DELETE files_categories FROM files_categories JOIN files ON file_id = files.id JOIN categories ON category_id = categories.id WHERE url = :url AND slug = :slug";
			$params = [
					':url' => $old_url,
					':slug' => $category
					];
			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', queryMaker($sql, $params), 'Update file references');
		}

		// reference new file
		if (($file_updated || $mode === 'add') && $category) {
			$sql = "INSERT INTO files_categories (file_id, category_id) SELECT (SELECT id FROM files WHERE url = :url), (SELECT id FROM categories WHERE slug = :slug) ON DUPLICATE KEY UPDATE file_id = file_id";
			$params = [
						':url' => $path,
						':slug' => $category,
						];
			
			$query = $pdo->prepare($sql);
			$query->execute($params);
			logger('/log/files.log', queryMaker($sql, $params), 'Update file references');
		}
		
		// Update statuts time in server settings
		if ($mode === 'add' && $category === 'statuts') {
			$data = json_decode(file_get_contents($server_content_file), true);
			$data['statuts']['date'] = $_POST['date'];
			file_put_contents($server_content_file, json_encode($data, JSON_PRETTY_PRINT));
		}

		// fetch updated data
		$sql = "SELECT name, size FROM files WHERE url = :url";
		$query = $pdo->prepare($sql);
		$query->execute([':url' => $path]);
		$file = $query->fetch();
	
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