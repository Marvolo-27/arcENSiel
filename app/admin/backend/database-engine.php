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
		$sql = "SELECT url, title, picture_path, description, tags, type, author, date, end_date, color, status FROM articles JOIN types ON types.id = CAST(articles.type AS SIGNED INTEGER)";
	} elseif ($type === 'users') {
		$sql = "SELECT id, username, role FROM users";
	} elseif ($type === 'tags') {
		$sql = "SELECT * FROM tags";
	} elseif ($type === 'types') {
		$sql = "SELECT * FROM types";
	} elseif ($type === 'logs') {
		$sql = "SELECT * FROM logs ORDER BY time DESC";
	} elseif ($type === 'files') {
		$cat = $input['category'] ?? false;
	
		$sql = $cat ? "SELECT url, name, description from files WHERE category = :cat":"SELECT url, name, size, uploader, type, used_by, description, status, created_at, updated_at from files ORDER BY name ASC";
		$params = $cat ? [':cat' => $cat]:null;
	}
	
	$query = $pdo->prepare($sql);
	$query->execute($params);
	$output = $query->fetchALL(PDO::FETCH_ASSOC);
	
	// logger('/log/queries.log', queryMaker($sql, $params), 'Fetch entries');

	http_response_code(200);
	$sendback = ['message' => 'Entries fetched successfully', 'sql-data' => $output];

	isset($_SESSION['user_id']) ? $sendback['user'] = ['id' => $_SESSION['user_id'], 'name' => $_SESSION['username']]:null; 

	echo json_encode($sendback);
	exit;
	}

if ($action === 'archive') {
	$sql = "UPDATE articles SET status = :status WHERE url = :url;";
	$params = [':url' => $input['slug'], ':status' => $input['status']];

	$query = $pdo->prepare($sql);
	$query->execute($params);
	
	logger('/log/articles.log', queryMaker($sql, $params), 'Update article status');

	http_response_code(200);
	echo json_encode(['message' => 'Updated successfully']);
	exit;
	}

if ($action === 'delete') {
	preg_match('/^(articles|types|tags)$/',$input['type'], $matches);
	$table = $matches[0] ?? null; // redundant but prevent sql injection
	$type = preg_match('/^articles$/',$table) ? 'url':'slug';

	// get deleted tag id and update articles tags
	if ($table === 'tags') {
		$query = $pdo->prepare('SELECT id FROM tags WHERE slug = :slug');
		$query->execute([':slug' => $input['slug']]);
		$id = $query->fetch()['id'];
		
		$sql = "WITH query AS (SELECT :id AS query)
				UPDATE articles JOIN query SET tags = REGEXP_REPLACE(
				tags, 
				CONCAT('^(.*)(\\\\[|,)(', query, ')(,|\\\\])(.*)$'),
				CASE
					WHEN REGEXP_LIKE(tags, CONCAT('^\\\\[', query, '\\\\]$')) THEN null
					WHEN REGEXP_LIKE(tags, CONCAT('^\\\\[', query, ',.*$')) THEN '$2$5'
					WHEN REGEXP_LIKE(tags, CONCAT('^.*,', query, '\\\\]$')) THEN '$1$4'
					WHEN REGEXP_LIKE(tags, CONCAT('^.*,', query, ',.*$')) THEN '$1$2$5'
					ELSE '$1$2$3$4$5'
				END), updated_at = :time";
				// Assigns deleted tag id to a temp table 'query' conatining one column 'query'
				// Joins articles with this temp table to access the query (w/o binding with pdo everywhere)
				// Regex replace, with replacement decided based on structure
				// $1 : start of text, $2 : first separator, $3 : tag id, $4 : second separator, $5 : end of text
		$query = $pdo->prepare($sql);
		$query->execute([':id' => $id, ':time' => date('Y-m-d H:i:s', time())]);
	}

	// adapt articles type
	if ($table === 'types') {
		$query = $pdo->prepare('UPDATE articles SET type = "0" WHERE type = :type');
		$query->execute([':type' => $input['value']]);
	}

	// delete entry
	$sql = "DELETE FROM ".$table." WHERE ".$type." = :url;";
	$params = [':url' => $input['slug']];

	$query = $pdo->prepare($sql);
	$query->execute($params);
	
	logger('/log/articles.log', queryMaker($sql, $params), 'Delete entry '.$input['slug'].' from '.$input['type']);

	http_response_code(200);
	echo json_encode(['message' => 'Deleted successfully']);
	exit;
	}

if ($action === 'check') {
	$pswd = $input['password'] ?? null;
	$method = $_SESSION['method'];
	$username = $_SESSION['username'];
	
	$query = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ? AND auth_type = ?");
	$query->execute([$username, $method]);
	$user = $query->fetch();
	
	if ($user && password_verify($password_token . $pswd, $user['password_hash'])) {// existing user  - validated credentials
			http_response_code(200);
			$response = ['message' => 'valid credentials'];
			echo json_encode($response);
			exit;
	} else {
			http_response_code(400);
			$response = ['message' => 'invalid credentials'];
			echo json_encode($response);
			exit;
	}
	}

if ($action === 'update') {
	// request extract
	$raw_data = $input['data'];
	$section = $type === 'users' ? 'users':'articles';

	try {
		foreach ($raw_data as $row) {
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
				$sql = "UPDATE articles SET url = :url, picture_path = :path, title = :title, author = :author, date = :date, end_date = :enddate, type = :type, updated_at = :time WHERE id = :id;"; // extract id of added article
				$params =  [
							':url' => $row['url'],
							':path' => $row['picture_path'],
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
			
			} elseif ($type === 'tags') {
				$sql = "UPDATE tags SET name = :name, slug = :slug WHERE id = :id;"; // extract id of added article
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':id' => $row['id']
							];
				$query = $pdo->prepare($sql);
				$query->bindValue(':name', $params[':name']);
				$query->bindValue(':slug', $params[':slug']);
				$query->bindValue(':id', $params[':id'], PDO::PARAM_INT);
				$query->execute();
			} elseif ($type === 'types') {
				$sql = "UPDATE types SET name = :name, slug = :slug, color = :color WHERE id = :id;"; // extract id of added article
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':color' => $row['color'],
							':id' => $row['id']
							];
				$query = $pdo->prepare($sql);
				$query->bindValue(':name', $params[':name']);
				$query->bindValue(':slug', $params[':slug']);
				$query->bindValue(':color', $params[':color']);
				$query->bindValue(':id', $params[':id'], PDO::PARAM_INT);
				$query->execute();
			}
			logger('\/log\/'.$section.'.log', queryMaker($sql, $params), 'Update data');
		}
		http_response_code(200);
		$message = 'queries completed successfully';
	} catch (PDOException $e) {
		http_response_code(500); // access authorized so success but failed to ouput sql
		echo json_encode(['error' => $e]);
		logger('\/log\/'.$section.'.log', 'Source: '.queryMaker($sql, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: Update data');
		exit;
	}

	$sendback = ['message' => $message];
	echo json_encode($sendback);
	exit;
}