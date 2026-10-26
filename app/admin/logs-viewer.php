<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

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
		<meta property="og:title" content="Logs">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		
		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>	
				<h1>Fichiers logs</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"></div>
			</div>
			
			<div class="box filters">
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
						<input class='checkbox' type='radio' id="mail-btn" name="table" value="mail">
						<label class='tag-item option blue' for="mail-btn">Mail</label>
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
					await tablesBuilder()

					// Tables buttons management
					Box('filters').inputs().forEach(item => item.onchange = e => {
							const token = e.target.value
							
							if (token === 'all') {
								logsTable.clearFilter()
							} else {
								logsTable.setFilter("section", "=", token)
							}
						})
					}
		
		async function tablesBuilder() {
				// fetch all required data
				await fetchLogs('#logs')
				
				// easy match table with their type
				tableMatch = {
							'logs': {'table': logsTable, 'source': JSON.stringify(logsTable.getData()), 'call': fetchLogs, 'selector': '#logs', 'type': 'logs'},
							}
				activeTable = tableMatch.logs // Force active table update
				
				tablesSwitcher()
		}

		Icon('refresh').onclick = logUpdater
		async function logUpdater(e) {
			const icon = e.target
			icon.show()
			icon.setAttribute('title','Synchronisation des logs')
			notify("Synchronisation en cours", "Récupération des logs depuis la base de données.", 'orange')
			await tablesBuilder()
			notify("Synchronisation terminée", "Les logs ont correctement été synchronisés.", 'green')
			icon.setAttribute('title','Synchroniser les logs')
			icon.hide()
			}
		</script>
	</body>
</html>