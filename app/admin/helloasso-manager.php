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
		<title>HelloAsso</title>
		
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
		#main .form-action-wrap {display: none}
		#main .form-action-wrap[active] {display: flex}
		</style>
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
			
			<div class="form-action-wrap linear">
				<div class='form-action'>
					<button class='action-btn save'>Sauvegarder</button>
				</div>
				<div class='form-action'>
					<button class='action-btn reset'>Réinitialiser</button>
				</div>
				<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>
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
												'helloasso': {'table': haTable, 'source': JSON.stringify(haTable.getData()), 'call': fetchHA, 'type': 'helloasso', 'selector': '#helloasso'},
												'goodies': {'table': goodiesTable, 'source': JSON.stringify(goodiesTable.getData()), 'call': fetchGoodies, 'type': 'goodies', 'selector': '#goodies'},
												'dq7': {'table': dq7Table, 'source': JSON.stringify(dq7Table.getData()), 'call': fetchDQ7, 'type': 'dq7', 'selector': '#dq7'}
												}
									
									tablesListener(true)
									}
		
		// refresh helloasso data
		document.querySelector('.icon.refresh').onclick = haUpdater;
		
		async function haUpdater(e) {
			notify('Synchronisation depuis HelloAsso', 'orange')
			e.target.findParent('.icon').setAttribute('active','');
			e.target.findParent('.icon').setAttribute('title','Fetching data from HelloAsso');

			jsonData = JSON.stringify({'csrf':document.querySelector('#csrf').dataset.csrf})

			response = await fetch('/api/helloasso/update', {
									method: "POST",
									body: jsonData,
									headers: { 'Content-Type': 'application/json' }
									})
			
			if (!response.ok) {
				json = await response.json();

				notify('Erreur lors de la syncronisation : '+json.error, 'red')
				e.target.findParent('.icon').removeAttribute('active');
				e.target.findParent('.icon').setAttribute('title','Synchroniser les données');
				return
			}

			await fetchHA('#helloasso')
			await fetchGoodies('#goodies')
			await fetchDQ7('#dq7')
			
			e.target.findParent('.icon').removeAttribute('active');
			e.target.findParent('.icon').setAttribute('title','Synchroniser les données');
			notify('Synchronisation terminée', 'green')
			}

		async function saveEdits() {
					notify('Sauvegarde en cours', 'orange')

					// fetch active table
					type = activeTable.type;
					table = activeTable.table;
					
					// csrf token
					token = document.querySelector('#csrf').dataset.csrf;
					
					await sleep(500);
					
					// extract data
					srcdata = Array.from(goodiesTable.getData()).map(data => {return {"id": data.id, "delivered": data.delivered, "registered": data.registered}});

					jsonData = JSON.stringify({'data':srcdata,'csrf':document.querySelector('#csrf').dataset.csrf})

					response = await fetch('/api/helloasso/edit', {
									method: "POST",
									body: jsonData,
									headers: { 'Content-Type': 'application/json' }
									})
					
					data = await response.json();
					
					if (!response.ok) {
						alert(response.code)
						return
					}
					
					notify('Données mises à jour', 'green')
					
					// Reset table state
					activeTable.source = JSON.stringify(activeTable.table.getData())
					resetState(activeTable)
				}
		
		document.querySelector('.icon.download').onclick = downloadData;
		function downloadData() {
				if (activeTable === undefined) {
					notify('Sélectionner une table pour télécharger les données', 'red')
					return
				}
			
				// download text file
				var c = document.createElement("a");
				data = activeTable.table.getData();
				
				type = document.querySelector('.table[active]').getAttribute('id').replace('#','');
				
				c.download = `${type}_data.json`;
				var t = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
				c.href = window.URL.createObjectURL(t);
				c.click();
				setTimeout(function() { URL.revokeObjectURL(c.href); }, 1500);
		}

		
		document.querySelector('#overlay-valid .action-btn.cancel').onclick = e => {
				document.querySelector("#overlay-valid").removeAttribute("active");
				pendingAction = {};
		}

		// go on with requested action
		document.querySelector('#overlay-valid .action-btn.validate').onclick = e => {
				document.querySelector("#overlay-valid").removeAttribute("active");
				pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
				pendingAction = {};
		}

		// show panel to save changes
		document.querySelector('.action-btn.save').onclick = e => {
				pendingAction.function = saveEdits;
				document.querySelector('#overlay-valid').toggleAttribute('active')
		}

		// reset edits
		document.querySelector('.action-btn.reset').onclick = e => {
				pendingAction.function = resetTabledata;
				document.querySelector('#overlay-valid').toggleAttribute('active')
		}
		</script>
	</body>
</html>