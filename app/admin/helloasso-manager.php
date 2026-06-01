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
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}

?>

<!DOCTYPE html>
<html>
	<head>
		<title>HelloAsso Board</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="HelloAsso Board">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		<script src='/files/assets/js/api.js'></script>
		
		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img' ></div></a>
				<h1>HelloAsso</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"><span class='img'></span></div>
				<div class='icon download inline large hoverable dynamic' title="Télécharger les données"><span class='img'></span></div>
			</div>
			
			<div class="box">
				<div class="input-container checkboxes">
					<span>
						<input class='checkbox' type='radio' id="helloasso-btn" name="table" value="helloasso">
						<label class='tag-item option blue' for="helloasso-btn">HelloAsso</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="goodies-btn" name="table" value="goodies">
						<label class='tag-item option blue' for="goodies-btn">Goodies</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="dq7-btn" name="table" value="dq7">
						<label class='tag-item option blue' for="dq7-btn">Drama Queer 7</label>
					</span>
				</div>
			</div>

			<div class='table no-bound' id='helloasso'></div>
			<div class='table no-bound' id='goodies'></div>
			<div class='table no-bound' id='dq7'></div>
			
			<div class="form-action-wrap data-actions linear">
				<div class='form-action'>
					<button class='action-btn responsive save'>Sauvegarder</button>
				</div>
				<div class='form-action'>
					<button class='action-btn responsive reset'>Réinitialiser</button>
				</div>
				<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
			</div>
		</div>
		
		<?php include($root . '/app/templates/overlay_validation.html') ?>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// on-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									// fetch all required data
									await fetchHA('#helloasso');
									await fetchGoodies('#goodies');
									await fetchDQ7('#dq7')
									
									// easy match table with their type
									tableMatch = {
												'helloasso': {'table': haTable, 'node': Table('helloasso'), 'source': JSON.stringify(haTable.getData()), 'call': fetchHA, 'type': 'helloasso', 'selector': '#helloasso'},
												'goodies': {'table': goodiesTable, 'node': Table('goodies'), 'source': JSON.stringify(goodiesTable.getData()), 'call': fetchGoodies, 'type': 'goodies', 'selector': '#goodies'},
												'dq7': {'table': dq7Table, 'node': Table('dq7'), 'source': JSON.stringify(dq7Table.getData()), 'call': fetchDQ7, 'type': 'dq7', 'selector': '#dq7'}
												}
									
									tablesListener(true)
									}
		
		// refresh helloasso data
		Icon('refresh').onclick = haUpdater;
		
		async function haUpdater(e) {
			if (!syncerAPI(e, 'HelloAsso')) return

			await fetchHA('#helloasso')
			await fetchGoodies('#goodies')
			await fetchDQ7('#dq7')
			
			notify("Synchronisation terminée", "Les données ont été mises à jour depuis le serveur HelloAsso.", 'green')
			}

		async function saveEdits() {
					notify("Sauvegarde en cours", "Les modifications effectuées sont en train d'être sauvegardées. Veillez à ne pas quitter la page.", 'orange')

					// fetch active table
					type = activeTable.type;
					table = activeTable.table;
					
					await sleep(500);
					
					// extract data
					srcdata = Array.from(goodiesTable.getData()).map(data => ({"id": data.id, "delivered": data.delivered, "registered": data.registered}));

					jsonData = JSON.stringify({'data':srcdata, 'csrf':csrfToken})
					response = await fetchAPI('/api/helloasso/edit', jsonData)
					
					if (!response.ok) {
						notify("Erreur lors de la mise à jour des données", response.body.error, 'red')
						return
					}
					
					notify("Données mises à jour", "Les modifications ont été enregistrées sur le serveur.", 'green')
					
					// Reset table state
					resetTableData(activeTable, true)
				}
		
		// Show download options
		Icon('download').onclick = helloassoDownload;
		function helloassoDownload() {
				if (activeTable === undefined) {
					notify("Choix invalide", "Veuillez sélectionner une table pour en télécharger les données.", 'red')
					return
				}
				
				type = activeTable.type;
				data = activeTable.table.getData();

				blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
				download(blob, `${type}_data.json`)
		}

		// Cancel pending action
		Overlay('validate').button('cancel').onclick = e => {
				e.container().hide();
				pendingAction = {};
		}

		// go on with requested action
		Overlay('validate').button('validate').onclick = e => {
				e.container().hide();
				pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
				pendingAction = {};
		}

		// show panel to save changes
		Button('save').onclick = e => {
				pendingAction.function = saveEdits;
				Overlay('validate').toggle()
		}

		// reset edits
		Button('reset').onclick = e => {
				pendingAction.function = resetTabledata;
				Overlay('validate').toggle()
		}
		</script>
	</body>
</html>