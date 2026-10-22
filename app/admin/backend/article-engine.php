<?php
header('Content-Type: application/json');

// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

$csrf = $_POST['csrf-token'] ?? null;
if (!$csrf) {$csrf = $_GET['token'] ?? null;}

// Restrict to admins
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

if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
    http_response_code(401);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

// root folder
$folder = $serverroot . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$rootpath = '/files/uploads/';

// General variables
$action = $_POST['action'] ?? $_GET['action'] ?? null;

//// Save on server draft data
if ($action === 'draft') {
	$_SESSION['post-data'] = $_POST;
	echo json_encode(['message' => 'Draft generated']);
	exit;
}

//// url availability checkbox
if ($action === 'check') {
	$base_url = $_GET['content'] ?? null;
	$mode = $_GET['mode'] ?? null;

	if ($base_url) {// test url validity
		$query = $pdo->prepare("SELECT id FROM articles WHERE slug = ?");
		$query->execute([$base_url]);
		
		if ($mode === 'locked') {// find first available url
			$count = 1;
			$url = $base_url;
			while ($query->fetch()) {//find first valid url
				$url = $base_url . '-' . $count;
				$query->execute([$url]);
				$count++;
			}
			
			http_response_code(200);
			echo json_encode(['message' => 'URL disponible.', 'value' => $url]);
		} else {
			if ($query->fetch()) {
				http_response_code(400);
				echo json_encode(['error' => 'URL indisponible.']);
			} else {
				http_response_code(200);
				echo json_encode(['message' => 'URL valide générée', 'value' => $base_url]);
			}
		}
		exit;
	}
}

//// article data
$status = $_POST['status'];
$url = str_replace('https://arcensiel.org/articles/', '', $_POST['art-url']);
$title = $_POST['art-title'];
$author = $_POST['art-author'];
$date = $_POST['art-date'] ?? null;
$enddate = $_POST['art-end-date'] !== '' ? $_POST['art-end-date'] : null;
$desc = $_POST['art-desc'];
$content = $_POST['art-content'];
$tags_list = json_decode($_POST['art-tags'], true); // format : [{'slug': slug, 'name':name}, ]
$type_raw = json_decode($_POST['type'], true); // format : {'slug': slug, 'name':name', 'color':color, 'new':bool}
$updated = date("Y-m-d H:i:s", time());
$file_desc = $_POST['file-description'];
$file_uploaded = $_FILES['art-pic']['error'] !== 4;
$file_updated = $_POST['pic-updated'] == 'true';

// test url uniqueness
$query = $pdo->prepare("SELECT id FROM articles WHERE slug = ?");
$query->execute([$url]);

if ($query->fetch() && $status === 'creation') {
	http_response_code(250);
	echo json_encode(['code' => '400', 'error' => "L'URL spécifiée '$url' est déjà attribuée."]);
	exit;
}

/// Handle tags
$tags = []; // list of IDs of article tags
$sqlTagsMatch = "INSERT INTO articles_tags (article_id, tag_id) VALUES ";
// Insert article's tags
foreach ($tags_list as $tag) {
	// extract id of tag in tags table
	$sql = "INSERT INTO tags (slug, name) VALUES (:slug, :name) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);";
	$params = [
			':slug' => $tag['slug'],
			':name' => $tag['name']
			];
	$query = $pdo->prepare($sql);
	$query->execute($params);
	$tag_id = (int) $pdo->lastInsertId(); // get the id of the tag (either new or already existing)
	$tags[] = $tag_id;
	$sqlTagsMatch = $sqlTagsMatch . "(:article, $tag_id),"; // pairs tag with article in articles_tags

	logger('/log/articles.log', queryMaker($sql, $params).' Insert ID: '.$tag_id, 'Extract tag id');
	}
$sqlTagsMatch = substr($sqlTagsMatch, 0, -1) . " ON DUPLICATE KEY UPDATE article_id = article_id"; // inserts all non-existing pairs : works with update and creation

/// Handle type
$sql = "INSERT INTO types (slug, name, color) VALUES (:slug, :name, :color) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);";
$params = [
		':slug' => $type_raw['slug'],
		':name' => $type_raw['name'],
		':color' => $type_raw['color'] ?? '#cccccc'
		];
$query = $pdo->prepare($sql);
$query->execute($params);
$type = (int) $pdo->lastInsertId();

/// Handle picture path
if ($file_uploaded) {// File object passed = image uploaded
	$picture = $_FILES['art-pic']; // array containing file info : name, type, tmp localization, error, size

		// filename data
	$src_filename = $file_uploaded ? $_POST['filename'] : $picture['name']; // prevent abusive file renaming for on-server files
	$extension = '.' . strtolower(pathinfo($src_filename, PATHINFO_EXTENSION));		// file extension
	$raw_filename = pathinfo($src_filename, PATHINFO_FILENAME);						// raw filename

	$flat_filename = transliterator_transliterate('Any-Latin; Latin-ASCII', $raw_filename); // convert all characters to Latin ASCII alphabet
	$filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $flat_filename); 					// proofed filename w/o extension
	$path = $rootpath . $filename . $extension; // absolute path

		// find first unique path for picture
	$query = $pdo->prepare("SELECT id from files WHERE url = ?");
	$query->execute([$path]);

	$count = 1;
	while ($query->fetch()) {
		$path = $rootpath . $filename . "_" . $count . $extension;
		$query->execute([$path]);
		$count++;
	}

	// stores picture in files
	if (move_uploaded_file($picture['tmp_name'], str_replace($rootpath, $folder, $path))) {
		logger('/log/files.log', "New file loaded at: $path", 'Upload new file');
	} else {
		http_response_code(500);
		echo json_encode(['error' => "Upload failed for file '".$raw_filename."'".PHP_EOL."Check file size."]);
		logger('/log/files.log', "New file could not be uploaded to $path", '[ERROR] Failed: upload new file');
		exit;
	}
} else {// No File Object = image unchanged
	$path = $_POST['picture-path'];
}


// Insert article
// CHANGE LOGIC : YOU CAN'T RESET END_DATE TO NULL (UNDEFINED)
$sql = "INSERT INTO articles (slug, title, author, date, ".($enddate ? "end_date, ":'')."summary, content, type, updated_at)
		VALUES (:url, :title, :author, CAST(:date AS DATE), ".($enddate ? "CAST(:enddate AS DATE), ":"").":desc, :content, CAST(:type AS UNSIGNED), CAST(:updated AS DATETIME))
		ON DUPLICATE KEY UPDATE 
			title = VALUES(title),
			author = VALUES(author),
			date = VALUES(date),".PHP_EOL.
			($enddate ? "end_date = VALUES(end_date),".PHP_EOL:'').
			"summary = VALUES(summary),
			content = VALUES(content),
			type = VALUES(type),
			updated_at = VALUES(updated_at),
			id=LAST_INSERT_ID(id);";
$params = [
			':url' => $url,
			':title' => $title,
			':author' => $author,
			':date' => $date,
			':desc' => $desc,
			':content' => $content,
			':type' => $type,
			':updated' => $updated
			];
$enddate ? $params[':enddate'] = $enddate:null;

$query = $pdo->prepare($sql);
try {
	$query->execute($params);
	$article_id = (int) $pdo->lastInsertID(); // lastInsertId is a PDO method - id of added or updated article
	logger('/log/articles.log', queryMaker($sql, $params).'; Insert ID: '.$article_id, 'Add/Update article');
} catch (PDOException $e) {
	http_response_code(250); // access authorized so success but failed to ouput sql
	echo json_encode( ['code' => 500, 'error' => json_encode($e), 'query'=>$query, 'data'=>$_POST]);
	logger('/log/articles.log', 'Source: '.queryMaker($sql, $params).PHP_EOL.json_encode($e), '[ERROR] Failed: add/update article');
	exit;
}


// Insert file
if ($file_uploaded) {
	$sql = "INSERT INTO files (name, url, size, description, type, status, uploader) VALUES (:name, :url, CAST(:size AS UNSIGNED), :desc, :type, TRUE, :user)";
	$params = [
			':name' => end(explode('/', $path)),
			':url' => $path,
			':size' => $picture['size'],
			':desc' => $file_desc,
			':type' => $picture['type'],
			':user' => $_SESSION['username'],
		];

	try {
		$query->execute($params);
		$file_id = (int) $pdo->lastInsertID();
		logger('/log/files.log', queryMaker($sql, $params), 'Insert file in db');
	} catch (PDOException $e) {
		http_response_code(250); // access authorized so success but failed to ouput sql
		echo json_encode( ['code' => 500, 'error' => json_encode($e), 'query'=>$query, 'data'=>$_POST]);
		logger('/log/files.log', 'Source: '.queryMaker($sql, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: insert file in db');
		exit;
	}

} else {// handle referencing when used file is already on server
	// update description
	$sql = "UPDATE files SET description = :description, updated_at = :time WHERE url = :url";
	$params = [
			':description' => $file_desc,
			':url' => $path,
			':time' => date('Y-m-d H:i:s', time())
			];

	$query = $pdo->prepare($sql);
	$query->execute($params);
	logger('/log/files.log', queryMaker($sql, $params), 'Update file description');
}

// Handle cover references
if ($file_updated) {
	// dereference old file
	$old_url = $_POST['og-pic-path']; // url of file until now linked to an entry
	
	// fetch current use status
	$sql = "DELETE articles_covers FROM articles_covers JOIN files ON file_id = files.id WHERE url = :url AND article_id = :artid";
	$params = [
			':url' => $old_url,
			':artid' => $article_id
			];
	$query = $pdo->prepare($sql);
	$query->execute($params);
	
	logger('/log/files.log', queryMaker($sql, $params), 'Update file references');

}

if ($file_updated || $status === 'creation') {
	// reference new image
	$sql = "INSERT INTO articles_covers (file_id, article_id) SELECT (SELECT id FROM files WHERE url = :url), :artid ON DUPLICATE KEY UPDATE file_id = file_id";
	$params = [
				':url' => $path,
				':artid' => $article_id,
				];
	
	$query = $pdo->prepare($sql);
	$query->execute($params);
	logger('/log/files.log', queryMaker($sql, $params), 'Update file references');
}


// ! pdo prepare can only run one instruction at a time - if several are passed, nothing is done

// Insert all new pairs in match table
if (!empty($tags_list)) {
	try {
		$query = $pdo->prepare($sqlTagsMatch);
		$query->execute([':article' => $article_id]);
		logger('/log/articles.log', queryMaker($sqlTagsMatch, [':article' => $article_id]), 'Set article/tags cross-references');
	} catch (PDOException $e) {
		logger('/log/articles.log', 'Source: '.queryMaker($sqlTagsMatch, [':article' => $article_id]).PHP_EOL.json_encode($e), '[ERROR] Failed: Set article/tags cross-references');
	}
}

if ($status === 'edition') {// remove pairs linked with deleted tags
	$query = $pdo->prepare("SELECT tag_id FROM articles_tags WHERE article_id = :id");
	$query->bindValue(':id', $article_id, PDO::PARAM_INT);
	$query->execute();
	$id_list_raw = $query->fetchAll(PDO::FETCH_ASSOC);

	// extract deleted tags id
	$id_list = array_reduce($id_list_raw, function($carry, $item) use ($tags) {if (!in_array($item["tag_id"], $tags)) $carry[] = $item["tag_id"]; return $carry;}, []);

	if (!empty($id_list)) {
		$sql = "DELETE FROM articles_tags WHERE article_id = CAST(:id AS UNSIGNED) AND tag_id IN(";
		foreach ($id_list as $id) {// loop through all registered tags - if not in updated list, delete row
			$sql = $sql.$id.','; // with this syntax $id is directly seen as integer when sql is interpreted
			}
		$sql = substr($sql, 0, -1) . ")";
		$query = $pdo->prepare($sql);
		
		try {
			$query->execute([':id' => $article_id]);
			logger('/log/articles.log', queryMaker($sql, [':id' => $article_id]), 'Update article/tags cross-references');
		} catch(PDOException $e) {
			logger('/log/articles.log', 'Source: '.queryMaker($sql, [':id' => $article_id]).PHP_EOL.json_encode($e), '[ERROR] Failed: Update article/tags cross-references');
		}
	}
}
	
http_response_code(200);
echo json_encode(['message' => "Article $title ajouté avec succès", 'data' => ['titre' => $title, 'url' => $url, 'tags' => $tags, 'poster' => $path, 'date' => $date]]);
exit;
?>