<?php
session_start();

// Database connection and common variables
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
    echo json_encode(["error" => "Forbidden"]);
	exit;
}

?>

<!DOCTYPE html>
<html>
	<head>
		<title>Logs</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		
		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
		
		<style>
			/*!! News !!*/
			#placeholder {
				width: 95%;
				margin: auto;
				}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a>	
				<h1>Fichiers logs</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"><span class='img'></span></div>
			</div>
			
			<div class="box">
				<div class="input-container checkboxes">
					<span>
						<input class='checkbox' type='radio' id="all-btn" name="table" value="all" checked>
						<label class='tag-item option blue' for="all-btn">Tout</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="users-btn" name="table" value="users">
						<label class='tag-item option blue' for="users-btn">Utilisateurices</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="articles-btn" name="table" value="articles">
						<label class='tag-item option blue' for="articles-btn">Articles</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="files-btn" name="table" value="files">
						<label class='tag-item option blue' for="files-btn">Fichiers</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="helloasso-btn" name="table" value="helloasso">
						<label class='tag-item option blue' for="helloasso-btn">HelloAsso</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="paheko-btn" name="table" value="paheko">
						<label class='tag-item option blue' for="paheko-btn">Paheko</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="queries-btn" name="table" value="queries">
						<label class='tag-item option blue' for="queries-btn">Requêtes</label>
					</span>
				</div>
			</div>
			
			<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>
			<div class='table no-bound' id='logs' active></div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>		
		// on-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									// fetch all required data
									await fetchLogs('#logs');
									
									// easy match table with their type
									tableMatch = {
												'logs': {'table': logsTable, 'source': JSON.stringify(logsTable.getData()), 'call': fetchLogs, 'selector': '#logs', 'type': 'logs'},
												}
									activeTable = logsTable;
									
									tablesSwitcher()

									// Tables buttons management
									Array.from(document.querySelector('.input-container.checkboxes').children).forEach(
											item => {
												item.addEventListener('change', e => {
													tkn = e.target.findParent('span').querySelector('input').value;
													
													if (tkn === 'all') {
														logsTable.clearFilter()
													} else {
														logsTable.setFilter("section", "=", tkn)
													}
												})
											})
									}
		
		document.querySelector('.icon.refresh').onclick = logUpdater;
		
		async function logUpdater(e) {
			notify('Synchronisation des logs.', 'orange')
			e.target.findParent('.icon').setAttribute('active','');
			e.target.findParent('.icon').setAttribute('title','Syncing logs');

			await fetchLogs('#logs')
			
			e.target.findParent('.icon').removeAttribute('active');
			e.target.findParent('.icon').setAttribute('title','Synchroniser les logs');
			notify('Synchronisation terminée', 'green')
			}

		async function passwordChecker() {
				// pswd zone
				tag = document.querySelector('input#pswd');
				pswd = tag.value;
				
				// error message
				errorTag = tag.findParent('.box').querySelector('.error');
				
				// action buttons
				btn = document.querySelector('#overlay-auth .action-btn.validate');
				btnbis = document.querySelector('#overlay-auth .action-btn.cancel');
				btn.disabled = true;
				btnbis.disabled = true;
				btn.toggleAttribute('active')
				
				// active table
				table = activeTable.table;
				
				// reset state
				tag.removeAttribute('valid');
				errorTag.removeAttribute('active');
				
				// csrf token
				token = document.querySelector('#csrf').dataset.csrf;
				
				await sleep(500);
				
				jsonData = JSON.stringify({'action':'check','type':activeTable.type,'csrf':document.querySelector('#csrf').dataset.csrf, 'password':document.querySelector('#pswd').value})
				//console.log(jsonData)
				
				// update data
				response = await fetch('/admin/engine/database', {
										method: 'POST',
									    body: jsonData,
									    headers: { 'Content-Type': 'application/json' }
									    }
									)
				tag.value = '';
				btn.toggleAttribute('active');
				btn.disabled = false;
				btnbis.disabled = false;
				
				if (!response.ok) {
					tag.setAttribute('invalid','');
					errorTag.setAttribute('active','');

					return false
				} else {return true}
		}
		</script>
	</body>
</html>