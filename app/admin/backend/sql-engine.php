<?php
session_start();
header('Content-Type: application/json');

// request extract
$csrf = $_POST['csrf-token'] ?? null;
$query_raw = $_POST['sql-query'] ?? 'SELECT * FROM users';
$queries = explode(';', trim($query_raw));		// separate different queries

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

if ($_SESSION['username'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden: You don't have permission to access this resource."]);
	exit;
}


if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
    http_response_code(403);
    echo json_encode(["error" =>"Unauthorized: The requested ressource is not accessible."]);
	exit;
}

$output = [];
foreach ($queries as $query_text) {
	try {
		if ($query_text !== '') {
			$query = $pdo->prepare($query_text);
			$query->execute();
			logger('/log/queries.log', $query_text, 'SQL request');
			$output[] = $query->fetchALL(PDO::FETCH_ASSOC);
		}
		
		http_response_code(200);
		$message = 'queries completed successfully';
	} catch (PDOException $e) {
		http_response_code(250); // access authorized so success but failed to ouput sql
		echo json_encode(['error' => $e]);
		logger('/log/queries.log', 'Source: '.$query_text.';'.PHP_EOL.json_encode($e), '[ERROR] Failed: SQL request');
		exit;
	}
}
if (count($queries) === 1) $output = $output[0]; // reduce array if only one query

$sendback = ['message' => $message, 'output' => $output];
echo json_encode($sendback);
exit;

// EXAMPLES
// $query = $pdo->prepare("DESCRIBE articles");
// $query = $pdo->prepare("SELECT table_name FROM INFORMATION_SCHEMA.TABLES");
// $query = $pdo->prepare("DELETE FROM users WHERE username = ?");
// $query = $pdo->prepare("UPDATE users SET username = 'admin' WHERE username = ?");
// $query->execute(['admin']);
// SHOW INDEXES FROM tags; // show all constraints for table
// ALTER TABLE table_name ADD COLUMN new_column_name data_type AFTER existing_column;
// SELECT articles.url, articles.tags, group_concat(tags.slug separator ', ') AS tag_list FROM articles JOIN tags JOIN article_tags ON articles.id = article_id AND tags.id = tag_id GROUP BY article_id