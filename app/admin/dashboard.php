<?php
session_start();

$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Admin dashboard</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		
		<style>
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id="grid-wrap">
				<div class='icon float cross large'><span class='img'></span></div>
				
				<h1 class="head-info">
					Dashboard
				</h1>
				
				<div id="grid-container">
					<div class='grid-item-wrap'>
						<a href="/user/dashboard">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/username.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Profil utilisateur</span>
								</div>
							</div>
						</a>
					</div>
					
					<?php if ($_SESSION['username'] === 'admin'): ?>
					<div class='grid-item-wrap'>
						<a href="/admin/tools/sql">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/databases.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Gestion de la base de données</span>
								</div>
							</div>
						</a>
					</div>
					<?php endif; ?>

					<div class='grid-item-wrap'>
						<a href="/admin/tools/create/article">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/paper.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Créer un article</span>
								</div>
							</div>
						</a>
					</div>

					<div class='grid-item-wrap'>
						<a href="/admin/manage/database">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/gears.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Gestion des données</span>
								</div>
							</div>
						</a>	
					</div>
					
					<div class='grid-item-wrap'>
						<a href="/admin/manage/files">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/files.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Gestion des fichiers</span>
								</div>
							</div>
						</a>
					</div>
					
					<div class='grid-item-wrap'>
						<a href="/admin/manage/helloasso">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img class='invertable' src="/files/icons/helloasso_icon.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Récapitulatif HelloAsso</span>
								</div>
							</div>
						</a>	
					</div>

					<div class='grid-item-wrap'>
						<a href="/admin/manage/paheko">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/paheko_icon.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Récapitulatif Paheko</span>
								</div>
							</div>
						</a>	
					</div>
					
					<div class='grid-item-wrap'>
						<a href="/admin/logs">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/history.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Consulter les logs</span>
								</div>
							</div>
						</a>
					</div>
					
					<div class='grid-item-wrap'>
						<a href="https://arcensiel.frama.space" target="_blank_">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/logo_framaspace.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Accéder au Framaspace</span>
								</div>
							</div>
						</a>
					</div>

					<div class='grid-item-wrap'>
						<a href="https://arcensiel.paheko.frama.space" target="_blank_">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/logo_paheko.png">
								</div>
								
								<div class="grid-item-preview">
									<span>Accéder à l'espace Paheko</span>
								</div>
							</div>
						</a>
					</div>
					
					<?php if(in_array($_SESSION['username'], $ha_admins)): ?>
					<div class='grid-item-wrap'>
						<a href="https://admin.helloasso.com/arcensiel-association-queer-de-l-ens-lyon/accueil" target="_blank_">
							<div class='grid-item'>
								<div class='article-poster poster-fg icon-style'>
									<img src="/files/icons/logo_helloasso.svg">
								</div>
								
								<div class="grid-item-preview">
									<span>Accéder à HelloAsso</span>
								</div>
							</div>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>		
		</script>
	</body>
</html>