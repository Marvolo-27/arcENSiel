<?php
header('Content-Type: application/json');

// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;

if ($_SERVER['HTTP_X_REQUESTED_WITH'] !== 'fetch') {
		http_response_code(403);
		exit('403: Forbidden');
	}

// extract passed arguments
$action = $_GET['action'] ?? 'fetch';
$userid = $_SESSION['user_id'] ?? false;

if ($action === 'fetch') {
	$n = (int) $_GET['n'] ?? 0;
	$start = (int) $_GET['start'] ?? 0;

	$sql = "SELECT articles.slug AS url, description, title, tags, date, author, COALESCE(types.name, 'Indéfini') AS type, COALESCE(files.url, '/files/placeholder.png') AS picture_path, color
			FROM bookmarks
				JOIN articles ON bookmarks.article_id = articles.id
				LEFT JOIN types on COALESCE(articles.type, 0) = types.id
				LEFT JOIN articles_covers ON articles_covers.article_id = articles.id
				LEFT JOIN files ON file_id = files.id
			WHERE user_id = :userid AND status = 'published'
			ORDER BY added_on DESC";
	
	if ($n === 0) {
		if ($start !== 0) {
			$extension = " LIMIT 2000000000 OFFSET :start"; // all entries from offset
		}
	} else {
		if ($start === 0) {
			$extension = " LIMIT :n"; // reduced entries
			
		} else {
			$extension = " LIMIT :n OFFSET :start"; // reduces entries from offset
		}
	}
	$sql = $sql.$extension;

	logger('/log/queries.log', "Fetch bookmarks for user ".$_SESSION['username'], 'Fetch bookmarks',false);

	// select coresponding articles
	$query = $pdo->prepare($sql);
	$n > 0 ? $query->bindValue(':n', $n, PDO::PARAM_INT):null;
	$start > 0 ? $query->bindValue(':start', $start, PDO::PARAM_INT):null;
	$query->bindValue(':userid', $userid, PDO::PARAM_INT);
	$query->execute();
	$output = $query->fetchALL(PDO::FETCH_ASSOC);

	$query = $pdo->prepare("SELECT COUNT(*) FROM bookmarks JOIN articles ON article_id = articles.id WHERE user_id = :user AND status = 'published'");
	$query->execute([':user' => $user]);
	$total = $query->fetch(PDO::FETCH_ASSOC);

	http_response_code(200);
	echo json_encode(['metadata' => ['sent' => count($output), 'n' => $n, 'start_at' => $start, 'total' => $total['COUNT(*)']], 'data' => $output]);
	exit;
}

if ($action === 'update') {
	$mode = $_GET['mode'];
	$url = $_GET['url'];
	
	if (!$userid) {
		http_response_code(400);
		echo json_encode(['error' => "Aucun·e utilisateurice n'est actuellement connecté·e"]);
	}

	try {
		// get article id for passed url
		$query = $pdo->prepare("SELECT id FROM articles WHERE slug = :url");
		$query->execute([':url' => $url]);
		$id = (int) $query->fetch()['id'];
		
		if ($mode === 'mark') {
			// insert bookmark pair
			$sql = "INSERT INTO bookmarks (user_id, article_id) VALUES (:user, :article)";	
		} elseif ($mode === 'unmark') {
			// remove bookmark pair
			$sql = "DELETE FROM bookmarks WHERE user_id = CAST(:user AS UNSIGNED) AND article_id = CAST(:article AS UNSIGNED)";
		} else {
			// safety leverage to prevent invalid query parameter strings
			http_response_code(500);
			exit;
		}

		$params = [':user' => $userid, ':article' => $id];
		$query = $pdo->prepare($sql);
		$query->execute($params);
		logger('/log/users.log', "Article $url ".($mode === 'mark' ? 'added to':'removed from')." bookmarks for user ".$_SESSION['username'], ($mode === 'mark' ? 'Add':'Remove')." bookmark", false);

		// out
		http_response_code(200);
		echo json_encode(['message' => 'Bookmark status updated successfully.']);
	
	} catch (PDOException $e) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update bookmarks: '.json_encode($e)]);
		logger('/log/queries.log', "Source: ".queryMaker($sql, $params).PHP_EOL.json_encode($e), "Failed bookmarks request", false);
	}
}
