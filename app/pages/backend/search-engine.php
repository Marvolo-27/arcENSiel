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
$type = $_GET['type'] ?? null; // type filttering (extact)
$from = $_GET['from'] ?? null; // low-limit date
$to = $_GET['to'] ?? null; // high-limit date
$rawtags = $_GET['tags'] ?? null;
if ($rawtags) {
	$rawtags = explode(',', $rawtags);
	$tags = [];
	for ($i=0;$i<count($rawtags);$i++)  {$tags[":$i"] = $rawtags[$i];}
} else {
	$tags = false;
}

$filters = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY); // extrait les paramètres de requêtes GET (post '?')

// Number of results arguments
$n = (int) $_GET['n'] ?? 0;
$start = (int) $_GET['start'] ?? 0;

// base of SQL request
$fetchcore = "SELECT url, title, picture_path, description, tags, types.name AS type, author, date, end_date, color FROM articles JOIN types ON types.id = CAST(articles.type AS SIGNED INTEGER) WHERE";
$countcore = "SELECT COUNT(*) FROM articles JOIN types ON types.id = articles.type WHERE";
$params = [];


// search filters
$sql = '';
if ($search) {
	$sql = $sql." (LOCATE(:query, description) > 0 OR LOCATE(:query, title) > 0)";
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
	$tagsql = "SELECT article_id FROM article_tags JOIN tags ON tag_id = tags.id WHERE tags.slug IN (";
	foreach ($tags as $code => $tag) {
		$tagsql = $tagsql."$code, ";
		$params[$code] = $tag;
		}
	$tagsql = substr($tagsql, 0, -2).") GROUP BY article_id HAVING COUNT(article_id) = :nbtags";
	$sql = $sql." AND articles.id IN ($tagsql)";
}

// sorting
$sql = $sql." AND status = 'published' ORDER BY date DESC";

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
// $start > 0 ? $params[':start'] = $start:null;
// $n > 0 ? $params[':n'] = $n:null;

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
	// logger('/log/queries.log', json_encode($params), 'error');
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
// echo json_encode($output);
echo json_encode(['metadata' => ['sent' => count($output), 'n' => $n, 'start_at' => $start, 'total' => $total['COUNT(*)']], 'data' => $output]);
exit;
	
