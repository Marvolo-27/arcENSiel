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

		<style>
			.mainsection {
					height: 6em;
			}

			.subsection {
					top: 2.75em;
					left: 0;
					position: absolute !important;
					display: none !important;

					.checkbox:checked ~ & {display: flex !important;}
			}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>
				<h1>HelloAsso</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"></div>
				<div class='icon download inline large hoverable dynamic' title="Télécharger les données"></div>
			</div>
			
			<div class="box">
				<div class="input-container checkboxes mainsection">
					<span>
						<input class='checkbox' type='radio' id="campaigns-btn" name="table" value="campaigns">
						<label class='tag-item option blue' for="campaigns-btn">Campagnes</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="helloasso-btn" name="table" value="helloasso">
						<label class='tag-item option blue' for="helloasso-btn">HelloAsso</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="goodies-btn" name="table" value="goodies">
						<label class='tag-item option blue' for="goodies-btn">Goodies</label>
						<div class="input-container checkboxes subsection flush-left"></div>
					</span>
					<span>
						<input class='checkbox' type='radio' id="events-btn" name="table" value="events">
						<label class='tag-item option blue' for="events-btn">Événements</label>
						<div class="input-container checkboxes subsection flush-left"></div>
					</span>
					<span>
						<input class='checkbox' type='radio' id="donations-btn" name="table" value="donations">
						<label class='tag-item option blue' for="donations-btn">Dons</label>
						<div class="input-container checkboxes subsection flush-left"></div>
					</span>
					<span>
						<input class='checkbox' type='radio' id="memberships-btn" name="table" value="memberships">
						<label class='tag-item option blue' for="memberships-btn">Adhésions</label>
						<div class="input-container checkboxes subsection flush-left"></div>
					</span>
				</div>
			</div>

			<div class='table no-bound' id='campaigns'></div>
			<div class='table no-bound' id='helloasso'></div>
			<div class='table no-bound' id='goodies'></div>
			<div class='table no-bound' id='events'></div>
			<div class='table ' id='donations'></div>
			<div class='table' id='memberships'></div>
			
			<div class="actions-wrap data-actions linear">
				<div class='action-container'>
					<button class='action-btn responsive save'>Sauvegarder</button>
				</div>
				<div class='action-container'>
					<button class='action-btn responsive reset'>Réinitialiser</button>
				</div>
				<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
			</div>
		</div>
		
		<?php include($root . '/app/templates/overlay_validation.html') ?>
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// on-load logic
		window.addEventListener('load', tablesBuilder)
		
		async function tablesBuilder() {
				// fetch all required data
				await fetchCampaigns('#campaigns')
				await fetchHA('#helloasso')
				await fetchGoodies('#goodies')
				await fetchEvents('#events')
				await fetchDonations('#donations')
				await fetchMemberships('#memberships')
				
				// easy match table with their type
				tableMatch = {
							'campaigns': {'table': campaignsTable, 'node': Table('campaigns'), 'source': JSON.stringify(campaignsTable.getData()), 'call': fetchCampaigns, 'type': 'campaigns', 'selector': '#campaigns'},
							'helloasso': {'table': haTable, 'node': Table('helloasso'), 'source': JSON.stringify(haTable.getData()), 'call': fetchHA, 'type': 'helloasso', 'selector': '#helloasso'},
							'goodies': {'table': goodiesTable, 'node': Table('goodies'), 'source': JSON.stringify(goodiesTable.getData()), 'call': fetchGoodies, 'type': 'goodies', 'selector': '#goodies'},
							'events': {'table': eventsTable, 'node': Table('events'), 'source': JSON.stringify(eventsTable.getData()), 'call': fetchEvents, 'type': 'events', 'selector': '#events'},
							'donations': {'table': donationsTable, 'node': Table('donations'), 'source': JSON.stringify(donationsTable.getData()), 'call': fetchDonations, 'type': 'donations', 'selector': '#donations'},
							'memberships': {'table': membershipsTable, 'node': Table('memberships'), 'source': JSON.stringify(membershipsTable.getData()), 'call': fetchMemberships, 'type': 'memberships', 'selector': '#memberships'}
							}
				activeTable = tableMatch[activeTable?.type] // Force active table update

				tablesListener(true)				
		}

		// refresh helloasso data
		Icon('refresh').onclick = helloassoUpdater
		async function helloassoUpdater(e) {
			if (!syncerAPI(e, 'HelloAsso')) return
			await tablesBuilder()
			notify("Synchronisation terminée", "Les données ont été mises à jour depuis le serveur HelloAsso.", 'green')
			}

		async function saveEdits() {
					notify("Sauvegarde en cours", "Les modifications effectuées sont en train d'être sauvegardées. Veillez à ne pas quitter la page.", 'orange')

					// fetch active table
					type = activeTable.type;
					table = activeTable.table;
					
					await sleep(500);
					
					// extract data
					srcdata = Array.from(goodiesTable.getData()).map(data => ({"id": data.id, "delivered": data.delivered, "registered": data.registered}))

					jsonData = JSON.stringify({'data':srcdata, 'csrf':csrfToken})
					response = await fetchAPI('/api/helloasso/edit', jsonData)
					
					if (!response.ok) {
						notify("Erreur lors de la mise à jour des données", response.body.error, 'red')
						return
					}
					
					notify("Données mises à jour", "Les modifications ont été enregistrées sur le serveur.", 'green')
					
					// Reset table state
					resetTabledata(activeTable, true)
				}
		
		// Show download options
		Icon('download').onclick = helloassoDownload
		function helloassoDownload() {
				if (activeTable === undefined) {
					notify("Choix invalide", "Veuillez sélectionner une table pour en télécharger les données.", 'red')
					return
				}
				
				type = activeTable.type;
				data = activeTable.table.getData()

				blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" })
				download(blob, `${type}_data.json`)
		}

		// handle requested action
		Overlay('validate').button('validate').onclick = e => transfer(e)
		Overlay('validate').button('cancel').onclick = e => transfer(e, false)

		// show panel to save changes
		Button('save').onclick = e => {
				pendingAction.function = saveEdits
				Overlay('validate').toggle()
		}

		// reset edits
		Button('reset').onclick = e => {
				pendingAction.function = resetTabledata
				Overlay('validate').toggle()
		}
		</script>
	</body>
</html>