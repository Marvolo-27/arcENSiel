<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

// Display toast on first login
if (isset($_SESSION['message'])) {
	$message = $_SESSION['message'];
	unset($_SESSION['message']); // remove for future page load
} else {
	$message = false;
}

$artTags = getTags();
?>

<!DOCTYPE html>
<html>
	<head>
		<title>arcENSiel - Historique des publications</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Historique des publications">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id='grid-wrap'>
				<div class="news-header">
					<h1>Historique de nos publications</h1>
				</div>
				
				<div id="grid-container">
				</div>
				
				<div id="news-footer">
					<div class="news-nav-wrap">
						<div class="spinner"></div>
						<div class="news-count"></div>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader)
		async function loader(e)  {
									await buildNewsFlow(0, 2 * nGridItems)
									}
		
		window.onscroll = () => fetchControl('/engine/news')
		</script>
	</body>
</html>