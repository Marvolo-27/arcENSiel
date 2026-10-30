<?php
header('Content-Type: application/json');

// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

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
				SELECT articles.id AS id, articles.status AS status, articles.slug AS url, title, COALESCE(files.url, '/files/placeholder.png') AS picture_path, COALESCE(files.name, 'placeholder.png') AS poster_name, summary, tags_list.tags AS tags, COALESCE(articles.type, 0) AS type, author, date, end_date, color
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
		$sql = "SELECT categories.id AS id, categories.slug AS slug, categories.name AS name, sections.slug AS section, removable, COUNT(file_id) AS files FROM categories JOIN sections ON section_id = sections.id LEFT JOIN files_categories ON categories.id = category_id GROUP BY categories.slug";
	} elseif ($type === 'logs') {
		$sql = "SELECT * FROM logs ORDER BY time DESC";
	} elseif ($type === 'mails') {
		$sql = "SELECT m.message_id AS id, m.mode AS type, m.sent_from AS sender, m.send_to AS recipient, m.recipients AS recipients, m.cc AS cc, m.subject AS subject, CASE WHEN m.mode = 'sent' THEN m.sent_at WHEN m.mode = 'received' THEN m.received_at END AS time, m.html_body AS content, m.attachments AS attachments, r.message_id AS link FROM mails m LEFT JOIN mails r ON m.reference = r.sha256";
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
	preg_match('/^(articles|types|tags|categories)$/', $input['type'], $matches);
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
	$section = $type === 'users' ? 'users' : ($type === 'files' ? 'files':'articles');
	$failure = false;
	
	foreach ($raw_data as $row) {
		try {
			if ($type === 'users') {
				if ($row['username'] !== 'admin' && $row['username'] !== $_SESSION['username']) {
					$sql = "UPDATE users SET role = :role WHERE id = :id;";
					$params =  [
								':role' => $row['role'],
								':id' => $row['id']
								];
					$query = $pdo->prepare($sql);
					$query->execute($params);
				}
			
			} elseif ($type === 'articles') {
				$sql = "UPDATE articles SET slug = :url, title = :title, author = :author, date = :date, end_date = :enddate, type = :type, updated_at = :time WHERE id = CAST(:id AS UNSIGNED);";
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

				if ($row['picture_path'] !== '/files/placeholder.png') {
					$sql = "INSERT INTO articles_covers (article_id, file_id) SELECT :id, (SELECT id FROM files WHERE url = :url) ON DUPLICATE KEY UPDATE file_id = VALUES(file_id)";
					$params =  [
								':id' => $row['id'],
								':url' => $row['picture_path']
							];
					$query = $pdo->prepare($sql);
					$query->execute($params);
				}
			
			} elseif ($type === 'tags') {
				$sql = "INSERT INTO tags (id, name, slug) VALUES(".($row['id'] ? ':id' : 'NULL').", :name, :slug) ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)";
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug']
							];
				$row['id'] ? $params[':id'] = $row['id']:null;
				$query = $pdo->prepare($sql);
				$query->execute($params);
			
			} elseif ($type === 'types') {
				$sql = "INSERT INTO types (id, name, slug, color) VALUES (".($row['id'] ? ':id' : 'NULL').", :name, :slug, :color) ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), color = VALUES(color)";
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':color' => $row['color']
							];
				$row['id'] ? $params[':id'] = $row['id']:null;
				$query = $pdo->prepare($sql);
				$query->execute($params);
			
			} elseif ($type === 'categories') {
				$sql = "INSERT INTO categories (id, name, slug, section_id, removable) SELECT ".($row['id'] ? ':id' : 'NULL').", :name, :slug, (SELECT id FROM sections WHERE slug = :section), :removable ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)";
				$params = [
							':name' => $row['name'],
							':slug' => $row['slug'],
							':removable' => $row['removable'],
							':section' => $row['section']
							];
				$row['id'] ? $params[':id'] = $row['id']:null;
				$query = $pdo->prepare($sql);
				$query->execute($params);

			} elseif ($type === 'files') {
				$sql = "UPDATE files SET name = :name, url = :url, description = :desc, uploader = :uploader, updated_at = :time WHERE id = CAST(:id AS UNSIGNED)";
				$params =  [
							':name' => $row['name'].'.'.$row['extension'],
							':url' => str_replace($row['basename'], $row['name'], $row['url']),
							':desc' => $row['description'],
							':uploader' => $row['uploader'],
							':id' => $row['id'],
							':time' => date('Y-m-d H:i:s', time())
							];
				$query = $pdo->prepare($sql);
				$query->execute($params);

				// Rename file
				if ($row['name'] !== $row['basename']) {
					if ($serverroot.rename($row['url'], $serverroot.str_replace($row['basename'], $row['name'], $row['url']))) {
						logger('\/log\/files.log', "Le fichier '".$row['basename']."' a été renommé '".$row['name']."'.", 'Rename file');
					} else {
						logger('\/log\/files.log', "Le fichier '".$row['basename']."' n'a pas pu être renommé.", '[ERROR] Failed: Rename file');
					}
				}
			}

			logger('\/log\/'.$section.'.log', queryMaker($sql, $params), 'Update data');
		
		http_response_code(200);
		$message = 'Queries completed successfully';
		} catch (PDOException $e) {
			$failure = true;
			logger('\/log\/'.$section.'.log', 'Source: '.queryMaker($sql, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: Update data');
		}
	}

	if ($type === 'files') {
		filesManager();
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

if ($action === 'insert') {
	$type = $input['type'];

	// redudancy to prevent SQL injection
	$typematch = ['categories'];
	if (!in_array($type, $typematch)) $type = 'categories';

	if ($type === 'categories') {
		$slug = $input['slug'];
		$name = $input['name'];
		$section = $input['section'];

		$sql = "INSERT INTO categories (slug, name, section_id, sort_order, removable) SELECT :slug, :name, (SELECT id FROM sections WHERE slug = :section), (SELECT MAX(sort_order) + 1 FROM categories), 1";
		$params = [
					':slug' => $slug,
					':name' => $name,
					':section' => $section
					];
		
		try {
			$query = $pdo->prepare($sql);
			$query->execute($params);
			http_response_code(200);
			logger('/log/files.log', "Create new file category '".$slug."' in ".$section.".", 'New file category');
			$sendback = ['message' => 'New category created.'];
		} catch (PDOException $e) {
			http_response_code(500);
			logger('/log/files.log', "Source:".queryMaker($sql, $params).PHP_EOL.json_encode($e), '[ERROR] Failed: new file category');
			$sendback = ['error' => 'Failed to create new category.'];
		}
	}

	echo json_encode($sendback);
}

if ($action === 'sort') {
	$type = $input['type'];

	// redudancy to prevent SQL injection
	$typematch = ['categories'];
	if (!in_array($type, $typematch)) $type = 'categories';

	if ($type === 'categories') {
		$data = $input['data'];
		$categories = $data['categories'];
		$files = $data['files'];
		$section = $input['section'];

		$updated_categories = array_filter($categories, function($cat) {return $cat['initialIndex'] !== $cat['currentIndex'];});
		$updated_files = array_filter($files, function ($file) {return $file['initialIndex'] !== $file['currentIndex'] || $file['initialCategory'] !== $file['currentCategory'];});

		if ($updated_categories) {
			// Reset values to prevent duplicate keys
			$sql = "UPDATE categories SET sort_order = NULL WHERE slug IN (".str_repeat('?,', count($updated_categories) -1)."?)";
			$params = array_reduce($updated_categories, function($carry, $item) {$carry[] = $item['category']; return $carry;});
			$query = $pdo->prepare($sql);
			$query->execute($params);

			foreach ($updated_categories as $category) {
				$sql = "UPDATE categories SET sort_order = :rank WHERE slug = :slug";
				$params =  [
							':rank' => $category['currentIndex'],
							':slug' => $category['category']
				];
				
				try {
					$query = $pdo->prepare($sql);
					$query->execute($params);
					http_response_code(200);
					logger('/log/files.log', "Reorder categories in ".$section.".", "Sort categories in ".$section);
					$sendback = ['message' => 'Items sorted.'];
				} catch (PDOException $e) {
					http_response_code(500);
					logger('/log/files.log', "Source:".queryMaker($sql, $params).PHP_EOL.json_encode($e), '[ERROR] Failed: Sort '.$section);
					$sendback = ['error' => 'Failed to sort items.'];
				}

			}
		}

		if ($updated_files) {
			// Reset values to prevent duplicate keys
			$sql = "UPDATE files_categories SET sort_order = NULL WHERE file_id IN (".str_repeat('?,', count($updated_files) - 1)."?)";
			$params = array_reduce($updated_files, function($carry, $item) {$carry[] = $item['id']; return $carry;});
			$query = $pdo->prepare($sql);
			$query->execute($params);

			foreach ($updated_files as $file) {
				$sql = "UPDATE files_categories SET files_categories.sort_order = :rank, category_id = (SELECT id from categories WHERE slug = :category) WHERE file_id = CAST(:id AS UNSIGNED)";
				$params =  [
							':rank' => $file['currentIndex'],
							':category' => $file['currentCategory'],
							':id' => $file['id']
				];

				try {
					$query = $pdo->prepare($sql);
					$query->execute($params);
					http_response_code(200);
					logger('/log/files.log', "Reorder files in ".$section.".", "Sort files in ".$section);
					$sendback = ['message' => 'Items sorted.'];
				} catch (PDOException $e) {
					http_response_code(500);
					logger('/log/files.log', "Source:".queryMaker($sql, $params).PHP_EOL.json_encode($e), '[ERROR] Failed: Sort '.$section);
					$sendback = ['error' => 'Failed to sort items.'];
				}
				
			}
		}
	}

	echo json_encode($sendback);
}