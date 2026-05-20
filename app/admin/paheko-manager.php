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
			/*!! News !!*/
			/*#placeholder {
				width: 95%;
				margin: auto;
				}
			
			#main .external.auth-action {
						display:none;
						width: 95%;
						margin: auto;
						margin-top: 1.5em;
						}*/
			
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
			
			.box.download {
					& .mode-wrap {
							display: none;
							margin: 1em 0;

							&[active] {display: block}
							}


			}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a>	
				<h1>Paheko</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"><span class='img'></span></div>
				<div class='icon download inline large hoverable dynamic' title="Télécharger les données"><span class='img'></span></div>
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

					<div class="mode-wrap">
						<input type="checkbox" id="download-mode" value="deletion" name="delete"><label for="download-mode">Sauvegarder le fichier sur le serveur</label>
					</div>
				</div>

				<div class='external form-action-wrap linear'>
					<div class='form-action'>
						<div class='action-btn validate spinnable' disabled><span class='text'>Valider</span><span class="spinner"></span></div>
					</div>
					<div class='form-action'>
						<div class='action-btn cancel'>Annuler</div>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>			
		// on-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									// fetch all required data
									await fetchCategories('#categories');
									await fetchSubscriptions('#subscriptions');
									await fetchStocks('#stocks');
									
									// easy match table with their type
									tableMatch = {
												'categories': {'table': categoriesTable, 'source': JSON.stringify(categoriesTable.getData()), 'call': fetchCategories, 'selector': '#categories', 'type': 'categories'},
												'subscriptions': {'table': subscriptionsTable, 'source': JSON.stringify(subscriptionsTable.getData()), 'call': fetchSubscriptions, 'selector': '#subscriptions', 'type': 'subscriptions'},
												'stocks': {'table': stocksTable, 'source': JSON.stringify(stocksTable.getData()), 'call': fetchStocks, 'selector': '#stocks', 'type': 'stocks'},
												}
									
									tablesListener(false, '.input-container.checkboxes.mainsection')
									}
		
		// refresh paheko data
		Icon('refresh').onclick = pahekoUpdater;
		
		async function pahekoUpdater(e) {
				if (!syncerAPI(e, 'Paheko')) return

				await fetchCategories('#categories');
				await fetchSubscriptions('#subscriptions');
				await fetchStocks('#stocks');
				
				notify("Synchronisation terminée", "Les données ont été mises à jour depuis le serveur Paheko.", 'green')
				}
		
		// download backup file
		Input('download-type').onchange = e => {
			tag = Overlay('validate').button('validate')
			wrap = Overlay('validate').child('.mode-wrap')
			e.target.value === '' ? tag.disable():tag.enable()

			if (e.target.value !== '') ['database', 'archive'].includes(e.target.value) ? wrap.show():wrap.hide();
		};
		Icon('download').onclick = e => Overlay('validate').toggle();
		
		Overlay('validate').button('cancel').onclick = e => {
			if (e.target.getAttribute('disabled') === null) {
				e.container().toggle();
				e.container().button('validate').disable();
				e.container().child('.mode-wrap').hide();
				Input('download-type').firstElementChild.selected = true;
			}
		}
		
		Overlay('validate').button('validate').onclick = e => {
			if (e.target.findParent('.action-btn').getAttribute('disabled') === null) {
				pahekoDownload(e)
			}
		}

		async function pahekoDownload(e) {
				tag  = e.target.findParent('.action-btn')
				cncl = e.target.findParent('.form-action').nextElementSibling.firstElementChild

				type = Input('download-type').value;
				mode = Input('download-mode').checked ? 'backup':'basic';
				
				if (type === 'table') {// json file download
					blob = await fetchBlob('/admin/api/paheko/get/all')
					date = new Date().toISOString()
					filename = 'paheko-output_'+date+'.json'
				
				} else {// paheko file download
					notify("Téléchargement en cours", "Le fichier demandé est en cours de téléchargement depuis Paheko.", 'orange')
					tag.toggle('disabled')
					cncl.toggle('disabled')
					
					jsonData = JSON.stringify({'csrf':csrfToken,'mode':mode, 'type':type})
					response = await fetchAPI('/api/paheko/download', jsonData)

					if (!response.ok) {
						notify("Erreur lors de la synchronisation", response.body.error, 'red')

						if (!response.body.available) return
					}
					
					// Convert the base64 string to a byte array (required for blob): each byte of the file is represented as an integer in the array
						// uintarray is just a representation of the raw byte data deduced from base64 string
					bytes = Uint8Array.fromBase64(response.body.data.content);
					blob = new Blob([bytes], {type: response.body.data.type});
					filename = response.body.data.name
 
					tag.toggle('disabled')
					cncl.toggle('disabled')
					notify("Téléversement terminé", "Le fichier a bien été ajouté sur le serveur d'arcENSiel.", 'green')
				}

				Overlay('validate').hide();

				// download text file
				download(blob, filename)
				}

		</script>
	</body>
</html>