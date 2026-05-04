<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['HTTP_X_REQUESTED_WITH'] !== 'fetch') {
		http_response_code(403);
		exit('403: Forbidden');
	}

// extract passed arguments
$n = (int) $_GET['n'] ?? 0;
$start = (int) $_GET['start'] ?? 0;

$sql = "SELECT url, title, picture_path, description, tags, types.name AS type, types.slug AS typeslug, author, date, end_date, color FROM articles JOIN types ON types.id = CAST(articles.type AS SIGNED INTEGER) WHERE status = 'published' ORDER BY date DESC";
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

//DEBUG: logger('/log/queries.log', queryMaker($sql, [':n' => $n, ':start' => $start]), "Fetch articles data", false);

// select coresponding articles
$query = $pdo->prepare($sql);
$n > 0 ? $query->bindValue(':n', $n, PDO::PARAM_INT):null;
$start > 0 ? $query->bindValue(':start', $start, PDO::PARAM_INT):null;
$query->execute();
$output = $query->fetchALL(PDO::FETCH_ASSOC);

$query = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE status = 'published'");
$query->execute();
$total = $query->fetch(PDO::FETCH_ASSOC);

http_response_code(200);
// echo json_encode($output);
echo json_encode(['metadata' => ['sent' => count($output), 'n' => $n, 'start_at' => $start, 'total' => $total['COUNT(*)']], 'data' => $output]);
exit;
	
