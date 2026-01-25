<?php
session_start();
header('Content-Type: application/json');

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


//// Save on server draft data
$action = $_POST['action'] ?? null;
if ($action === 'draft') {
	$_SESSION['post-data'] = $_POST;
	exit;
}

//// url livecheck: passed with GET
$base_url = $_GET['url'] ?? null;
$mode = $_GET['mode'] ?? null;

if ($base_url) {// test url validity
	$query = $pdo->prepare("SELECT id FROM articles WHERE url = ?");
	$query->execute([$base_url]);
	
	if ($mode == 'locked') {
		$count = 1;
		$url = $base_url;
		while ($query->fetch()) {//find first valid url
			$url = $base_url . '-' . $count;
			
			$query = $pdo->prepare("SELECT id FROM articles WHERE url = ?");
			$query->execute([$url]);
		}
		
		http_response_code(200);
		echo json_encode(['url' => $url]);
		exit;
	} else {
		if ($query->fetch()) {
			http_response_code(400);
			echo json_encode(['error' => 'URL indisponible']);
			exit;
		} else {
			http_response_code(200);
			echo json_encode(['url' => $base_url]);
			exit;
		}
		
		
	}
}

//// article data
$status = $_POST['status'];
$url = str_replace('https://arcensiel.org/articles/', '', $_POST['art-url']);
$title = $_POST['art-title'];
$author = $_POST['art-author'];
$date = $_POST['art-date'] ?? null;
$enddate = $_POST['art-end-date'] ?? null;
$desc = $_POST['art-desc'];
$content = $_POST['art-content'];
$tags_list = json_decode($_POST['art-tags'], true); // format : [{'slug': slug, 'name':name'}, ]
$typeRaw = json_decode($_POST['type'], true); // id of type
$updated = date("Y-m-d H:i:s", time());
$file_uploaded = $_FILES['art-pic']['error'] !== 4;
$file_updated = $_POST['pic-updated'] ?? false;

// Handle tags
$tags = []; // create list of ids for article tags
$sqlTagsMatch = "INSERT INTO article_tags (article_id, tag_id) VALUES "; // to add into tagsmatching afterwards
// Insert article tags
foreach ($tags_list as $tag) {
	// extract id of tag in tags table
	$sql = "INSERT INTO tags (slug, name) VALUES (:slug, :name)
			ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);";
	$params = [':slug' => $tag['slug'], ':name' => $tag['name']];
	$query = $pdo->prepare($sql);
	$query->execute($params);
	$tag_id = (int) $pdo->lastInsertId(); // get the id of the tag (either new or already existing)

	logger('/log/articles.log', queryMaker($sql, $params).'; Insert ID: '.$tag_id, 'Extract tag id');

	$tags[] = $tag_id;

	// pairs tag with article in article_tags
	$sqlTagsMatch = $sqlTagsMatch . "(:article, $tag_id),";
		// get the id of the tag (either new or already existing)
	}
$sqlTagsMatch = substr($sqlTagsMatch, 0, -1) . " ON DUPLICATE KEY UPDATE article_id=article_id;"; // inserts all non-existing pairs : works with update and creation

// Handle type
$sql = "INSERT INTO types (slug, name, color) VALUES (:slug, :name, :color)
		ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);";
$params = [':slug' => $typeRaw['slug'], ':name' => $typeRaw['name'], ':color' => $typeRaw['color'] ?? '#cccccc'];
$query = $pdo->prepare($sql);
$query->execute($params);
$type = $pdo->lastInsertId();


// test url uniqueness
$query = $pdo->prepare("SELECT id FROM articles WHERE url = ?");
$query->execute([$url]);

if ($query->fetch() && $status === 'creation') {
	http_response_code(250);
	echo json_encode(['code' => '400', 'error' => "L'URL spécifiée - $url - est déjà attribuée."]);
	exit;
}

// picture data
if ($file_uploaded) {// File object passed = image uploaded
	$picture = $_FILES['art-pic']; // array containing file info : name, type, tmp localization, error, size

		// filename data
	$extension = '.' . strtolower(pathinfo($picture['name'], PATHINFO_EXTENSION));		// file extension
	$raw_filename = pathinfo($picture['name'], PATHINFO_FILENAME);							// raw filename

	$flat_filename = transliterator_transliterate('Any-Latin; Latin-ASCII', $raw_filename); 					// convert all characters to Latin ASCII alphabet
	$filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $flat_filename); 	// proofed filename w/o extension
	$raw_path = $rootpath . $filename . $extension; // absolute path

	// change to relative path
	$path = str_replace($root, '', $raw_path);
	$path = str_replace("\\", '/', $path);

		// find first unique path for picture
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

	// stores picture in files
	if (move_uploaded_file($picture['tmp_name'], str_replace($rootpath, $folder, $raw_path))) {
		logger('/log/files.log', "New file loaded at: $path", 'Upload new file');;
		//echo "File stored at: $dest";
	} else {
		http_response_code(500);
		echo json_encode(['error' => "Upload failed for file '".$raw_filename."'".PHP_EOL."Check file size."]);
		// echo json_encode(['error' => "Échec de la récupération du fichier $filename from {$picture['tmp_name']} to $path"]);
		logger('/log/files.log', "New file could not be uploaded to $path", 'Failed: upload new file');
		exit;
	}
} else {// No File Object = image unchanged on file upload
	$path = $_POST['picture-path'];
}


// Insert file
if ($file_uploaded) {
	$sql = "INSERT INTO files (name, url, used_by, size, description, type, status, uploader, category, subcategory) VALUES (:name, :url, :reference, :size, :desc, :type, TRUE, :user, 'poster', 'article')";
	$params = [
			':name' => $picture['name'],
			':url' => $path,
			':reference' => 'articles/'.$url,
			':size' => $picture['size'],
			':desc' => preg_replace('#\.[a-zA-Z]+$#', '', $picture['name']),
			':type' => $picture['type'],
			':user' => $_SESSION['username'],
		];

	$query = $pdo->prepare($sql);
	foreach ($params as $param => $value) {
		$query->bindValue($param, $value, $param === ':size' ? PDO::PARAM_INT:PDO::PARAM_STR);
	}
	try {
		$query->execute();
		logger('/log/files.log', queryMaker($sql, $params), 'Insert file in db');
	} catch (PDOException $e) {
		http_response_code(250); // access authorized so success but failed to ouput sql
		echo json_encode( ['code' => 500, 'error' => json_encode($e), 'que'=>$query, 'data'=>$_POST]);
		logger('/log/files.log', json_encode($e), '[ERROR] Failed: insert file in db');
		exit;
	}

	if ($status === 'edition') {// deref old file
		$old_url = $_POST['og-pic-path']; // url of file up to now linked to an entry
		
		// fetch current use status
		$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
		$query->execute([':url' => $old_url]);
		$result = $query->fetch();

		$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
		$params = [
			':reference' => preg_replace("#articles/$url,?#", '', $result['used_by']),
			':url' => $old_url,
			':time' => date('Y-m-d H:i:s', time())
			];

		$query = $pdo->prepare($sql);
		$query->execute($params);
		logger('/log/files.log', queryMaker($sql, $params), 'Update references (add article)');
	}
} else {// handle referencing when used file is already on server
	// reference new image
	if ($file_updated || $status === 'creation') {
		// fetch current use status
		$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
		$query->execute([':url' => $path]);
		$result = $query->fetch();
		$ref = $result['used_by'];
		$comma = $ref ? ',':'';

		$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
		$params = [
					':reference' => $ref.$comma."articles/$url",
					':url' => $path,
					':time' => date('Y-m-d H:i:s', time())
					];

		$query = $pdo->prepare($sql);
		$query->execute($params);
		logger('/log/files.log', queryMaker($sql, $params), 'Update references (add article)');
	}

	// derefence
	if ($file_updated) {
		$old_url = $_POST['og-pic-path']; // url of file up to now linked to an entry
		
		// fetch current use status
		$query = $pdo->prepare("SELECT used_by from files WHERE url = :url");
		$query->execute([':url' => $old_url]);
		$result = $query->fetch();

		$sql = "UPDATE files SET used_by = :reference, updated_at = :time WHERE url = :url";
		$params = [
			':reference' => preg_replace("#articles/$url,?#", '', $result['used_by']),
			':url' => $old_url,
			':time' => date('Y-m-d H:i:s', time())
			];

		$query = $pdo->prepare($sql);
		$query->execute($params);
		logger('/log/files.log', queryMaker($sql, $params), 'Update references (remove article)');
	}
}

// Insert article
// CHANGE LOGIC : YOU CAN'T RESET END_DATE TO NULL (UNDEFINED)
$sql = "INSERT INTO articles (url, picture_path, title, author, date, ".(!$enddate ? "end_date, ":"")."description, content, tags, type, updated_at)
		VALUES (:url, :path, :title, :author, :date, ".(!$enddate ? ":enddate, ":"").":desc, :content, :tags, :type, :updated)
		ON DUPLICATE KEY UPDATE 
			picture_path = VALUES(picture_path),
			title = VALUES(title),
			author = VALUES(author),
			date = VALUES(date),".PHP_EOL."			".
			(!$enddate ? "end_date = VALUES(end_date),".PHP_EOL."			":"").
			"description = VALUES(description),
			content = VALUES(content),
			tags = VALUES(tags),
			type = VALUES(type),
			updated_at = VALUES(updated_at),
			id=LAST_INSERT_ID(id);";
$params = [
			':url' => $url,
			':path' => $path,
			':title' => $title,
			':author' => $author,
			':date' => $date,
			':desc' => $desc,
			':content' => $content,
			':tags' => json_encode($tags),
			':type' => $type,
			':updated' => $updated
];
!$enddate ? $params[':enddate'] = $enddate:null;

$query = $pdo->prepare($sql);
try {
	$query->execute($params);
	$art_id = (int) $pdo->lastInsertID(); // lastInsertId is a PDO method - id of added or updated article
	logger('/log/articles.log', queryMaker($sql, $params).'; Insert ID: '.$art_id, 'Add/Update article');
} catch (PDOException $e) {
	http_response_code(250); // access authorized so success but failed to ouput sql
	echo json_encode( ['code' => 500, 'error' => json_encode($e), 'que'=>$query, 'data'=>$_POST]);
	logger('/log/articles.log', 'Source: '.queryMaker($sql, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: add/update article');
	exit;
}

// ! pdo prepare can only run one instruction at a time - if several are passed, nothing is done

// Insert all new pairs in match table
try {
	logger('/log/articles.log', queryMaker($sqlTagsMatch, [':article' => $art_id]), 'Set article/tags cross-references');
	$query = $pdo->prepare($sqlTagsMatch);
	$query->execute([':article' => $art_id]);
} catch (PDOException $e) {
	logger('/log/articles.log', 'Source: '.queryMaker($sqlTagsMatch, [':article' => $art_id]).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: Set article/tags cross-references');
}

if ($status === 'edition') {// remove pairs linked with deleted tags
	$query = $pdo->prepare("SELECT tag_id FROM article_tags WHERE article_id = :id");
	$query->bindValue(':id', $art_id, PDO::PARAM_INT);
	$query->execute();
	$id_list_raw = $query->fetchAll(PDO::FETCH_ASSOC);
	
	$id_list = array_map(function($item) {return $item["tag_id"];}, $id_list_raw);
	
	foreach ($id_list as $id) {// lop through all registered tags - if not in updated list, delete row
		if (!in_array($id, $tags)) {
			$sql = "DELETE FROM article_tags WHERE article_id = :id AND tag_id = :tag_id";
			$query = $pdo->prepare($sql);
			$query->execute([':id' => $art_id, ':tag_id' => $id]);
			logger('/log/articles.log', queryMaker($sql, [':id' => $art_id, ':tag_id' => $id]), 'Update article/tags cross-references');
		}
	}
}
	
http_response_code(200);
echo json_encode(['message' => "Article $title ajouté avec succès", 'art-data' => ['titre' => $title, 'url' => $url, 'tags' => $tags, 'poster' => $path, 'date' => $date]]);
exit;
?>