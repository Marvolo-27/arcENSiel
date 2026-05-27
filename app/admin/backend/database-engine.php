<?php
session_start();
header('Content-Type: application/json');

// extract sent data
$input = json_decode(file_get_contents("php://input"), true);

$action = $input['action'] ?? 'fetch';
$type = $input['type'] ?? 'articles';
$csrf = $input['csrf'] ?? null;


// Restric to admin user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

if ($type === 'users') {
	if ($_SESSION['role'] !== 'admin') {
		http_response_code(403);
		echo json_encode(["error" => "Forbidden: You don't have permission to access this resource."]);
		exit;
		}
} else {
	if ($_SESSION['role'] !== 'admin') {
		http_response_code(403);
		echo json_encode(["error" => "Forbidden: You don't have permission to access this resource."]);
		exit;
		}
}


// authentificate request
if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
	null;
    http_response_code(403);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}


if ($action === 'fetch') {
	$params = null;
	if ($type === 'articles') {
		$sql = "WITH
					tags_list AS (
						SELECT article_id, GROUP_CONCAT(tag_id SEPARATOR ',') AS tags
						FROM articles_tags GROUP BY article_id)
				SELECT articles.id AS id, articles.status AS status, articles.slug AS url, title, COALESCE(files.url, '/files/placeholder.png') AS picture_path, COALESCE(files.name, 'placeholder.png') AS poster_name, summary, tags_list.tags AS tags, articles.type AS type, author, date, end_date, color, content
				FROM articles
					LEFT JOIN types ON types.id = articles.type
					LEFT JOIN tags_list ON articles.id = tags_list.article_id
					LEFT JOIN articles_covers ON articles.id = articles_covers.article_id
					LEFT JOIN files ON file_id = files.id";
	} elseif ($type === 'users') {
		$sql = "SELECT id, username, role, CASE WHEN id = ".$_SESSION['user_id']." THEN 1 ELSE 0 END AS active FROM users";
	} elseif ($type === 'tags') {
		$sql = "SELECT id, slug, name FROM tags";
	} elseif ($type === 'types') {
		$sql = "SELECT id, slug, name, color FROM types";
	} elseif ($type === 'categories') {
		$sql = "SELECT categories.slug AS slug, categories.name AS name, sections.slug AS section, removable, COUNT(file_id) AS files FROM categories JOIN sections ON section_id = sections.id LEFT JOIN files_categories ON categories.id = category_id GROUP BY categories.slug";
	} elseif ($type === 'logs') {
		$sql = "SELECT * FROM logs ORDER BY time DESC";
	}
	
	$query = $pdo->prepare($sql);
	$query->execute($params);
	$output = $query->fetchALL(PDO::FETCH_ASSOC);
	
	// logger('/log/queries.log', queryMaker($sql, $params), 'Fetch entries');

	http_response_code(200);
	echo json_encode($output);
	exit;
	}

if ($action === 'archive') {
	$sql = "UPDATE articles SET status = :status WHERE slug = :url;";
	$params = [':url' => $input['slug'], ':status' => $input['status']];

	$query = $pdo->prepare($sql);
	$query->execute($params);
	
	logger('/log/articles.log', queryMaker($sql, $params), 'Update article status');

	http_response_code(200);
	echo json_encode(['message' => 'Updated successfully']);
	exit;
	}

if ($action === 'delete') {
	preg_match('/^(articles|types|tags)$/', $input['type'], $matches);
	$table = $matches[0] ?? null; // redundant but prevent sql injection

	// delete entry
	$sql = "DELETE FROM ".$table." WHERE slug = :slug;";
	$params = [':slug' => $input['slug']];

	$query = $pdo->prepare($sql);
	$query->execute($params);
	
	logger('/log/articles.log', queryMaker($sql, $params), 'Delete entry '.$input['slug'].' from '.$input['type']);

	http_response_code(200);
	echo json_encode(['message' => 'Deleted successfully']);
	exit;
	}

if ($action === 'auth') {
	$pswd = $input['password'] ?? null;
	$method = $_SESSION['method'];
	$username = $_SESSION['username'];
	
	$query = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = :username AND auth_type = :auth");
	$query->execute([':username' => $username, ':auth' => $method]);
	$user = $query->fetch();
	
	if ($user && password_verify($password_token . $pswd, $user['password_hash'])) {// existing user  - validated credentials
			http_response_code(200);
			$response = ['message' => 'valid credentials'];
	} else {
			http_response_code(400);
			$response = ['error' => 'invalid credentials'];		
	}
	echo json_encode($response);
	exit;
	}

if ($action === 'update') {
	// request extract
	$raw_data = $input['data'];
	$section = $type === 'users' ? 'users':'articles';
	$failure = false;
	
	foreach ($raw_data as $row) {
		try {
			if ($type === 'users') {
				$sql = "UPDATE users SET username = :username, role = :role WHERE id = :id;";
				$params =  [
							':username' => $row['username'],
							':role' => $row['role'],
							':id' => $row['id']
							];
				$query = $pdo->prepare($sql);
				$query->execute($params);

				// update username in session if changed
				$row['id'] === $_SESSION['user_id'] ? $_SESSION['username'] = $row['username'] : null;
			
			} elseif ($type === 'articles') {
				$sql = "UPDATE articles SET slug = :url, title = :title, author = :author, date = :date, end_date = :enddate, type = :type, updated_at = :time WHERE id = CAST(:id AS UNSIGNED);"; // extract id of added article
				$params =  [
							':url' => $row['url'],
							':title' => $row['title'],
							':author' => $row['author'],
							':date' => $row['date'],
							':enddate' => $row['end_date'],
							':type' => $row['type'],
							':id' => $row['id'],
							':time' => date('Y-m-d H:i:s', time())
							];
				$query = $pdo->prepare($sql);
				$query->execute($params);

				$sql = "INSERT INTO articles_covers (article_id, file_id) SELECT :id, (SELECT id FROM files WHERE url = :url) ON DUPLICATE KEY UPDATE file_id = VALUES(file_id)"; // extract id of added article
				$params =  [
							':id' => $row['id'],
							':url' => $row['picture_path']
						];
				$query = $pdo->prepare($sql);
				$query->execute($params);
			
			} elseif ($type === 'tags') {
				$sql = "UPDATE tags SET name = :name, slug = :slug WHERE id = CAST(:id AS UNSIGNED);"; // extract id of added article
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':id' => $row['id']
							];
				$query = $pdo->prepare($sql);
				$query->execute($params);
			} elseif ($type === 'types') {
				$sql = "UPDATE types SET name = :name, slug = :slug, color = :color WHERE id = CAST(:id AS UNSIGNED);"; // extract id of added article
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':color' => $row['color'],
							':id' => $row['id']
							];
				$query = $pdo->prepare($sql);
				$query->execute($params);
			}
			logger('\/log\/'.$section.'.log', queryMaker($sql, $params), 'Update data');
		
		http_response_code(200);
		$message = 'queries completed successfully';
		} catch (PDOException $e) {
			$failure = true;
			logger('\/log\/'.$section.'.log', 'Source: '.queryMaker($sql, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: Update data');
		}
	}

	if ($failure) {
		http_response_code(500);
		echo json_encode(['error' => 'Some errors occured when updating data. Check logs.']);
		exit;
	}

	$sendback = ['message' => $message];
	echo json_encode($sendback);
	exit;
}