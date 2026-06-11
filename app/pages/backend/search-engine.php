<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['HTTP_X_REQUESTED_WITH'] !== 'fetch') {
		http_response_code(403);
		exit('403: Forbidden');
	}

// Passed filtering queries
$search = $_GET['query'] ?? null; // text request
$author = $_GET['author'] ?? null; // author filtering (not exact)
$type = $_GET['type'] ?? null; // type filtering (exact)
$from = $_GET['from'] ?? null; // low-limit date
$to = $_GET['to'] ?? null; // high-limit date
$rawtags = $_GET['tags'] ?? null;
if ($rawtags) {
	$rawtags = explode(',', $rawtags);
	$tags = [];
	for ($i=0;$i<count($rawtags);$i++) {$tags[":$i"] = $rawtags[$i];}
} else {
	$tags = false;
}

$filters = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY); // extrait les paramètres de requêtes GET (post '?')

// Number of results arguments
$n = (int) $_GET['n'] ?? 0;
$start = (int) $_GET['start'] ?? 0;

// base of SQL request
$fetchcore = "WITH tags_list AS (SELECT article_id, GROUP_CONCAT(tag_id SEPARATOR ',') AS tags FROM articles_tags GROUP BY article_id) SELECT articles.slug AS url, title, COALESCE(files.url, '/files/placeholder.png') AS picture_path, summary, tags_list.tags AS tags, types.name AS type, author, date, end_date, color FROM articles LEFT JOIN types ON types.id = COALESCE(articles.type, 0) LEFT JOIN tags_list ON articles.id = tags_list.article_id LEFT JOIN articles_covers ON articles.id = articles_covers.article_id LEFT JOIN files ON file_id = files.id WHERE";
$countcore = "SELECT COUNT(*) FROM articles LEFT JOIN types ON types.id = articles.type WHERE";
$params = [];


// search filters
$sql = '';
if ($search) {
	$sql = $sql." (LOCATE(:query, summary) > 0 OR LOCATE(:query, title) > 0)";
	$params[':query'] = $search;
} else {// placeholder to have other filters get correct syntax
	$sql = $sql." 1 = 1";
}
if ($author) {
	$sql = $sql." AND LOCATE(:author, author)";
	$params[':author'] = $author;
}
if ($type) {
	$sql = $sql." AND types.slug = :type";
	$params[':type'] = $type;
}
if ($from) {
	$sql = $sql." AND date >= :from";
	$params[':from'] = $from;
}
if ($to) {
	$sql = $sql." AND (date <= :end OR end_date <= :end)";
	$params[':end'] = $to;
}
if ($tags) {
	$tagsql = "SELECT article_id FROM articles_tags JOIN tags ON tag_id = tags.id WHERE tags.slug IN (";
	foreach ($tags as $code => $tag) {
		$tagsql = $tagsql."$code,";
		$params[$code] = $tag;
		}
	$tagsql = substr($tagsql, 0, -1).") GROUP BY article_id HAVING COUNT(article_id) = :nbtags";
	$sql = $sql." AND articles.id IN ($tagsql)";
}

if ($sql === '') {
	http_response_code(200);
	echo json_encode(['metadata' => ['sent' => 0, 'n' => $n, 'start_at' => $start, 'total' => 0], 'data' => '[]']);
	exit;
}

// sorting
$sql = $sql." AND articles.status = 'published' ORDER BY date DESC";

// number of results
$amount = '';
if ($n === 0) {
	if ($start !== 0) {
		$amount = $amount." LIMIT 2000000000 OFFSET :start"; // all entries from offset
	}
} else {
	if ($start === 0) {
		$amount = $amount." LIMIT :n"; // reduced entries
		
	} else {
		$amount = $amount." LIMIT :n OFFSET :start"; // reduces entries from offset
	}
}

// select coresponding articles
try {
	// fetch articles
	$query = $pdo->prepare($fetchcore . $sql . $amount);

	// bind int value
	$start > 0 ? $query->bindValue(':start', $start, PDO::PARAM_INT):null;
	$n > 0 ? $query->bindValue(':n', $n, PDO::PARAM_INT):null;
	$tags ? $query->bindValue(':nbtags', count($tags), PDO::PARAM_INT):null;
	
	// bind parameters
	foreach ($params as $param => $value) {$query->bindValue($param, $value);}
	$query->execute();
	$output = $query->fetchALL(PDO::FETCH_ASSOC);

	// fetch number of results
	$query = $pdo->prepare($countcore . $sql);
	foreach ($params as $param => $value) {$query->bindValue($param, $value);}
	$tags ? $query->bindValue(':nbtags', count($tags), PDO::PARAM_INT):null;
	$query->execute();
	$total = $query->fetch(PDO::FETCH_ASSOC);

	//logger('/log/queries.log', queryMaker($fetchcore.$sql.$amount, $params), 'Search articles');
} catch (PDOException $e) {
	http_response_code(500); // access authorized so success but failed to ouput sql
	echo json_encode(['error' => $e]);
	logger('/log/queries.log', 'Source: '.queryMaker($fetchcore.$sql.$amount, $params).';'.PHP_EOL.json_encode($e), '[ERROR] Failed: search articles');
	exit;
}
http_response_code(200);
echo json_encode(['metadata' => ['sent' => count($output), 'n' => $n, 'start_at' => $start, 'total' => $total['COUNT(*)']], 'data' => $output]);
exit;
	
