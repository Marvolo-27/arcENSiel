<?php
session_start();

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

echo null;

?>