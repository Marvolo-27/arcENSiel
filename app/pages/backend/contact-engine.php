<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

//// article data
$name = $_POST['name'];
$topic = $_POST['topic'];
$email = $_POST['mail'];
$content = $_POST['content'];

$delivery = sendMail(0, $email, 'marc.zuccalli@ens-lyon.fr', $name, $topic, $content, 'Associatif/arcENSiel/Website Requests', $email);

if ($delivery['status'] === 0) {
	$_SESSION['contact-message'] = 'sent';

	header('Location: /contact');
} elseif ($delivery['status'] === 1) {
	// reset error state
	$_SESSION['contact-error'] = 'true';

	// extract form content
	$_SESSION['contact-data'] = ['name' => $name, 'topic' => $topic, 'mail' => $email, 'content' => $content];

	header('Location: /contact');
} elseif ($status === 2) {
	// reset error state
	$_SESSION['contact-error'] = 'bad-mail';

	// extract form content
	$_SESSION['contact-data'] = ['name' => $name, 'topic' => $topic, 'mail' => $email, 'content' => $content];

	header('Location: /contact');
}

exit;
?>