<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

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
		<title>Nos actualités</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Nos actualités">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>

		<style>
			#flow-wrap {
				padding: 1em 0;
			}

			#flow-container {
				display: flex;
				flex-direction: column;
				gap: 5em;
			}

			.article-type {
				padding: .5em .75em;
				border-radius: .25em;
				margin-top: .75em;
				
				& h3 {margin: 0}

				& a {color: unset}
			}

			.article-item {
					padding: 0 2em;
					margin: auto;
					width: 90%;
					box-sizing: border-box;
					display: flex;
					flex-flow: column;
				}

				.metadata {
						font-size: .9em;
						color: var(--metadata-color);
						margin: .5em 0 .75em 0;

						& a {color: var(--metadata-color)}
						}
				
				.article-description {
						margin: 1em 0;
						}
				
				.visual {
						width: 70%;
						}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id='flow-wrap'>
				<div class="news-header">
					<h1>Nos actualités</h1>
				</div>
				
				<div id="flow-container">
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
									await buildNewsFlow(0, 4, '/engine/news', '', 'flow')
									}
		
		window.onscroll = () => fetchControl('/engine/news', '', 'flow')
		</script>
	</body>
</html>