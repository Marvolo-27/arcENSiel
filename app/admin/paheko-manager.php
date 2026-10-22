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
		<title>Paheko Board</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Paheko Board">
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
			
			.mode-wrap {
					margin: 1em 0;
			}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>	
				<h1>Paheko</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"></div>
				<div class='icon download inline large hoverable dynamic' title="Télécharger les données"></div>
			</div>
			
			<div class="box">
				<div class="input-container checkboxes mainsection">
					<span>
						<input class='checkbox' type='radio' id="categories-btn" name="table" value="categories">
						<label class='tag-item option blue' for="categories-btn">Catégories</label>
						<div class="input-container checkboxes subsection"></div>
					</span>
					<span>
						<input class='checkbox' type='radio' id="subscriptions-btn" name="table" value="subscriptions">
						<label class='tag-item option blue' for="subscriptions-btn">Adhésions</label>
						<div class="input-container checkboxes subsection"></div>
					</span>
					<span>
						<input class='checkbox' type='radio' id="stocks-btn" name="table" value="stocks">
						<label class='tag-item option blue' for="stocks-btn">Goodies</label>
					</span>
				</div>
			</div>
			
			<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>

			<div class='table' id='categories'></div>
			<div class='table' id='subscriptions'></div>
			<div class='table' id='stocks'></div>
		</div>
		
		<div class="body-overlay" id="overlay-validate">
			<div class="inner-overlay">
				<div class="header">
					<h4 class="title">
						Télécharger une sauvegarde
					</h4>
				</div>
				
				<div class='box download condensed'>
					<div class='input-container'>	
						<select name='type' id='download-type' class='boxentry' required>
							<option value='' disabled hidden selected>Type de sauvegarde</option>
							<option class="saveable" value='database'>Base de données (.sqlite)</option>
							<option class="saveable" value='archive'>Archive des fichiers (.zip)</option>
							<option value='table'>Données des tableaux (.json)</option>
						</select>
					</div>

					<div class="mode-wrap activable">
						<input type="checkbox" id="download-mode" value="deletion" name="delete"><label for="download-mode">Sauvegarder le fichier sur le serveur</label>
					</div>
				</div>

				<div class='external actions-wrap linear'>
					<div class='action-container'>
						<div class='action-btn validate spinnable' disabled><span class='text'>Valider</span><span class="spinner"></span></div>
					</div>
					<div class='action-container'>
						<div class='action-btn cancel'>Annuler</div>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>			
		// on-load logic
		window.addEventListener('load', tablesBuilder)
		
		async function tablesBuilder()  {
				// fetch all required data
				await fetchGroups('#categories')
				await fetchSubscriptions('#subscriptions')
				await fetchStocks('#stocks')
				
				// easy match table with their type
				tableMatch = {
							'categories': {'table': groupsTable, 'node': Table('categories'), 'source': JSON.stringify(groupsTable.getData()), 'call': fetchGroups, 'selector': '#categories', 'type': 'categories'},
							'subscriptions': {'table': subscriptionsTable, 'node': Table('subscriptions'), 'source': JSON.stringify(subscriptionsTable.getData()), 'call': fetchSubscriptions, 'selector': '#subscriptions', 'type': 'subscriptions'},
							'stocks': {'table': stocksTable, 'node': Table('stocks'), 'source': JSON.stringify(stocksTable.getData()), 'call': fetchStocks, 'selector': '#stocks', 'type': 'stocks'},
							}
				activeTable = tableMatch[activeTable?.type] // Force active table update

				tablesListener(false, '.input-container.checkboxes.mainsection')
				}
		
		// refresh paheko data
		Icon('refresh').onclick = pahekoUpdater
		async function pahekoUpdater(e) {
				if (!syncerAPI(e, 'Paheko')) return
				await tablesBuilder()
				notify("Synchronisation terminée", "Les données ont été mises à jour depuis le serveur Paheko.", 'green')
				}
		
		async function pahekoDownload(e) {
				const validate = Button('validate')
				const cancel   = Button('cancel')

				const type = Input('download-type').value
				const mode = Input('download-mode').checked ? 'backup':'basic';
				
				if (type === 'table') {// json file download
					const blob = await fetchBlob('/admin/api/paheko/get/all')
					const date = new Date().toISOString()
					const filename = 'paheko-output_'+date+'.json'
				
				} else {// paheko file download
					notify("Téléchargement en cours", "Le fichier demandé est en cours de téléchargement depuis Paheko.", 'orange')
					validate.disable()
					cancel.disable()
					
					const jsonData = JSON.stringify({'csrf':csrfToken,'mode':mode, 'type':type})
					const response = await fetchAPI('/api/paheko/download', jsonData)

					if (!response.ok) {
						notify("Erreur lors de la synchronisation", response.body.error, 'red')

						if (!response.body.available) return
					}
					
					// Convert the base64 string to a byte array (required for blob): each byte of the file is represented as an integer in the array
						// uintarray is just a representation of the raw byte data deduced from base64 string
					const bytes = Uint8Array.fromBase64(response.body.data.content)
					const blob = new Blob([bytes], {type: response.body.data.type})
					const filename = response.body.data.name
 
					validate.enable()
					cancel.disable()
					notify("Téléversement terminé", "Le fichier a bien été ajouté sur le serveur d'arcENSiel.", 'green')
				}

				Overlay('validate').hide()

				// download text file
				download(blob, filename)
				}
		
		// download backup file
		Input('download-type').onchange = e => {
			tag = Overlay('validate').button('validate')
			wrap = Overlay('validate').section('mode-wrap')
			types = ['database', 'archive']

			e.target.value === '' ? tag.disable():tag.enable()
			types.includes(e.target.value ?? null) ? wrap.show():wrap.hide()
		}
		Icon('download').onclick = () => Overlay('validate').toggle()
		
		Overlay('validate').button('validate').onclick = e => {
			if (e.target.parent('button').getAttribute('disabled') === null) {
				pahekoDownload(e)
			}
		}

		Overlay('validate').button('cancel').onclick = e => {
			if (e.target.getAttribute('disabled') === null) {
				Overlay('validate').toggle()
				Overlay('validate').button('validate').disable()
				Overlay('validate').section('mode-wrap').hide()
				Input('download-type').firstElementChild.selected = true;
			}
		}
		</script>
	</body>
</html>