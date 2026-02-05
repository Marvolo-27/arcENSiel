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
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Favoris</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>

		<style>
			.tags span:last-child {display: none}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id='grid-wrap'>
				<div class="news-header head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/user/dashboard"><div class='img'></div></a>
					<h1>Vos favoris</h1>
					<span></span>
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
		window.onscroll = e => fetchControl('/user/engine/bookmarks', '&action=fetch')

		// On-load logic
		window.addEventListener('load', loader);
		async function loader(e)  {
									// fetch most recent previews
									await newsBuilder(0, 2 * nGridItems, '/user/engine/bookmarks', '&action=fetch');
									document.querySelector('.news-header span').innerText = received === 0 ? "Vous n'avez pas d'article enregistré.":'';
									fetchControl('/user/engine/bookmarks', '&action=fetch')
									}
		</script>
	</body>
</html>