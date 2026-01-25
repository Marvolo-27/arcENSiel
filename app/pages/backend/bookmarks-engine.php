<?php

use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
session_start();
header('Content-Type: application/json');

if ($_SERVER['HTTP_X_REQUESTED_WITH'] !== 'fetch') {
		http_response_code(403);
		exit('403: Forbidden');
	}

// extract passed arguments
$action = $_GET['action'] ?? 'fetch';
$user = $_SESSION['user_id'];

if ($action === 'fetch') {
	$n = (int) $_GET['n'] ?? 0;
	$start = (int) $_GET['start'] ?? 0;

	$sql = "SELECT url, description, title, tags, date, author, types.name AS type, picture_path, color FROM bookmarks JOIN articles ON article_id = articles.id JOIN types on articles.type = types.id WHERE user_id = :user AND status = 'published' ORDER BY added_on DESC";
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
	$query->bindValue(':user', $user);
	$query->execute();
	$output = $query->fetchALL(PDO::FETCH_ASSOC);

	$query = $pdo->prepare("SELECT COUNT(*) FROM bookmarks JOIN articles ON article_id = articles.id WHERE user_id = :user AND status = 'published'");
	$query->execute([':user' => $user]);
	$total = $query->fetch(PDO::FETCH_ASSOC);

	http_response_code(200);
	// echo json_encode($output);
	echo json_encode(['metadata' => ['sent' => count($output), 'n' => $n, 'start_at' => $start, 'total' => $total['COUNT(*)']], 'data' => $output]);
	exit;
}

if ($action === 'update') {
	$mode = $_GET['mode'];
	$url = $_GET['url'];
	
	try {
		// get article id for passed url
		$query = $pdo->prepare("SELECT id FROM articles WHERE url = :url");
		$query->execute([':url' => $url]);
		$id = $query->fetch()['id'];
		
		if ($mode === 'mark') {
			// insert bookmark pair
			$sql = "INSERT INTO bookmarks (user_id, article_id) VALUES (:user, :article)";	
		} elseif ($mode === 'unmark') {
			// remove bookmark pair
			$sql = "DELETE FROM bookmarks WHERE user_id = :user AND article_id = :article";
		} else {
			// safety leverage to prevent invalid query parameter strings
			http_response_code(500);
			exit;
		}

		$params = [':user' => $user, ':article' => $id];
		$query = $pdo->prepare($sql);
		$query->execute($params);
		logger('/log/users.log', "Article $url ".($mode === 'mark' ? 'added to':'removed from')." bookmarks for user ".$_SESSION['username'], ($mode === 'mark' ? 'Add':'Remove')." bookmark", false);

		// out
		http_response_code(200);
		echo json_encode(['message' => 'Bookmark status updated successfully.']);
	
	} catch (PDOException $e) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update bookmarks: '.json_encode($e)]);
		logger('/log/queries.log', json_encode($e), "Failed bookmarks request", false);
	}
}
