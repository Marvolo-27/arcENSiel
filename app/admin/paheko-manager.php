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
		<title>Paheko</title>
		
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
			
			<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>
			<div class='table' id='categories'></div>
			<div class='table' id='subscriptions'></div>
			<div class='table' id='stocks'></div>
		</div>
		
		<div class="body-overlay" id="overlay-valid">
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
		document.querySelector('.icon.refresh').onclick = pahekoUpdater;
		
		async function pahekoUpdater(e) {
				notify('Synchronisation depuis Paheko', 'orange')
				e.target.findParent('.icon').setAttribute('active','');
				e.target.findParent('.icon').setAttribute('title','Fetching data from Paheko');

				jsonData = JSON.stringify({'csrf':document.querySelector('#csrf').dataset.csrf})

				response = await fetch('/api/paheko/fetch', {
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

				await fetchCategories('#categories');
				await fetchSubscriptions('#subscriptions');
				await fetchStocks('#stocks');
				
				e.target.findParent('.icon').removeAttribute('active');
				e.target.findParent('.icon').setAttribute('title','Synchroniser les données');
				notify('Synchronisation terminée', 'green')
				}
		
		// download backup file
		document.querySelector('#download-type').onchange = e => {
			tag = document.querySelector('#overlay-valid .action-btn.validate');
			wrap = document.querySelector('#overlay-valid .mode-wrap')
			e.target.value === '' ? tag.setAttribute('disabled',''):tag.removeAttribute('disabled')

			if (e.target.value !== '') ['database', 'archive'].includes(e.target.value) ? wrap.setAttribute('active', ''):wrap.removeAttribute('active')
		};
		document.querySelector('.icon.download').onclick = e => document.querySelector('#overlay-valid').toggleAttribute('active');
		document.querySelector('#overlay-valid .action-btn.cancel').onclick = e => {
			if (e.target.findParent('.action-btn').getAttribute('disabled') === null) {
				document.querySelector('#overlay-valid').toggleAttribute('active');
				document.querySelector('#overlay-valid .action-btn.validate').setAttribute('disabled', '');
				document.querySelector('#download-type').firstElementChild.selected = true;
			}
		}
		document.querySelector('#overlay-valid .action-btn.validate').onclick = e => {
			if (e.target.findParent('.action-btn').getAttribute('disabled') === null) {
				pahekoDownload(e)
			}
		}

		async function pahekoDownload(e) {
				tag = e.target.findParent('.action-btn')
				cncl = e.target.findParent('.form-action').nextElementSibling.firstElementChild

				type = document.querySelector('#download-type').value;
				mode = document.querySelector('#download-mode').checked ? 'backup':'basic';
				
				if (type === 'table') {// json file download
					response = await fetch('/admin/api/paheko/get/all')
					blob = await response.blob()
					date = new Date().toISOString()
					filename = 'paheko-output_'+date+'.json'
				
				} else {// paheko file download
					notify('Téléchargement depuis Paheko depuis Paheko', 'orange')
					tag.toggleAttribute('active')
					tag.toggleAttribute('disabled')
					cncl.toggleAttribute('disabled')
					jsonData = JSON.stringify({'csrf':document.querySelector('#csrf').dataset.csrf,'mode':mode, 'type':type})

					response = await fetch('/api/paheko/download', {
											method: "POST",
											body: jsonData,
											headers: { 'Content-Type': 'application/json' }
											})
					json = await response.json();
					// console.log(json)

					if (!response.ok) {
						notify('Erreur lors de la synchronisation : '+json.error, 'red')

						if (!json.available) {// paheko failed: no file fetched
							return
						}
					}
					
					// Convert the base64 string to a byte array (required for blob): each byte of the file is represented as an integer in the array
						// uintarray is just a representation of the raw byte data deduced from base64 string
					bytes = Uint8Array.fromBase64(json.data.content);
					blob = new Blob([bytes], {type: json.data.type});
					filename = json.data.name

					tag.toggleAttribute('active')
					tag.toggleAttribute('disabled')
					cncl.toggleAttribute('disabled')
					notify('Téléchargement sur le serveur terminé', 'green')
				}

				document.querySelector('#overlay-valid').removeAttribute('active');

				// download text file
				c = document.createElement("a");
				url = URL.createObjectURL(blob);
				
				c.download = filename;
				c.href = url;
				c.click();
				setTimeout(function() { URL.revokeObjectURL(c.href); }, 1500);
				}

		</script>
	</body>
</html>