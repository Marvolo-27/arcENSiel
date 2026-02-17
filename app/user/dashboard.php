<?php
session_start();

// must be connected
if (!isset($_SESSION['user_id'])) {
	$response = ['response_code' => 401, 'success' => false, 'message' => "Unauthorized"];
	echo json_encode($response);
	header("Location: /");
	exit;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Profil utilisateur</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<h1>Dashboard</h1>
			</div>
			
			<div id="grid-wrap">
				<div id="grid-container">
					<div class='grid-item-wrap'>
						<a href="/user/myaccount">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/id-pass.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Mon profil</span>
								</div>
							</div>
						</a>
					</div>
					
					<div class='grid-item-wrap'>
						<a href="/user/favoris">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/bookmark.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Mes favoris</span>
								</div>
							</div>
						</a>
					</div>

					<?php if($_SESSION['role'] === 'admin'): ?>
					<div class='grid-item-wrap'>
						<a href="/admin/dashboard">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/admin-tools.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Admin dashboard</span>
								</div>
							</div>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
	</body>
</html>