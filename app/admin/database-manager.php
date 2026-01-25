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

$artTags = getTags();
$artTypes = getTypes();
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Gestion des données</title>
		
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
		
		/*.external.auth-action {display: none}
		.external.auth-action::before {display: none}*/
		
		#main .external.auth-action {
					display:none;
					width: 95%;
					margin: auto;
					margin-top: 1.5em;
					}
		#main .form-action-wrap {display: none}
		#main .form-action-wrap[active] {display: flex}
		
		.inner-overlay .form-action-wrap {margin-top: 1em}


		.table .tabulator-cell.published {color: green; font-weight: bold;}
		.table .tabulator-cell.archived {color: red; font-weight: bold;}
		.table .tabulator-cell.draft {color: purple; font-weight: bold;}
		.table .tabulator-cell[changed] {background-color: #f8f688a6}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a>	
				<h1>Bases de données</h1>
			</div>
			
			<div class="box">
				<div class="input-container checkboxes">
					<span>
						<input class='checkbox' type='radio' id="users-btn" name="table" value="users">
						<label class='tag-item option blue' for="users-btn">Utilisateurices</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="articles-btn" name="table" value="articles">
						<label class='tag-item option blue' for="articles-btn">Articles</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="tags-btn" name="table" value="tags">
						<label class='tag-item option blue' for="tags-btn">Étiquettes</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="types-btn" name="table" value="types">
						<label class='tag-item option blue' for="types-btn">Catégories</label>
					</span>
				</div>
			</div>

			<div class='table' id='users'></div>
			<div class='table no-bound' id='articles'></div>
			<div class='table' id='tags'></div>
			<div class='table' id='types'></div>
			
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
		
		<?php include($root . '/app/templates/overlay_authentification.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// variables
		let typesCounter = {};

		// on-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									Object.entries(typesMatchTable).forEach(([key, val]) => {if (key !== '0') typesCounter[key] = val.name})
									
									// fetch all required data
									await fetchUsers('#users');
									await fetchArticles('#articles');
									await fetchTags('#tags');
									await fetchTypes('#types');
									
									// easy match table with their type
									tableMatch = {
												'users': {'table': usersTable, 'source': JSON.stringify(usersTable.getData()), 'call': fetchUsers, 'selector': '#users', 'type': 'users'},
												'articles': {'table': articlesTable, 'source': JSON.stringify(articlesTable.getData()), 'call': fetchArticles, 'selector': '#articles', 'type': 'articles'},
												'tags': {'table': tagsTable, 'source': JSON.stringify(tagsTable.getData()), 'call': fetchTags, 'selector': '#tags', 'type': 'tags'},
												'types': {'table': typesTable, 'source': JSON.stringify(typesTable.getData()), 'call': fetchTypes, 'selector': '#types', 'type': 'types'}
												}
									
									tablesListener(true)
									}

		async function archiveArticle(event, cell, toarchive) {
				notify('Mise à jour du statut', 'orange')
				
				// csrf token
				token = document.querySelector('#csrf').dataset.csrf;
				
				// dom objects
				const btn = event.target.findParent('.icon')
				const row = cell.getRow();
				const statusCell = row.getCell('status')
				const iconsCell = row.getCell('actions').getElement()
				visualIcon = iconsCell.querySelector('.visualize')
				modifyIcon = iconsCell.querySelector('.modify')

				// extract data
				const state = toarchive ? 'archived':'published';
				const slug = cell.getRow().getData().url;

				await sleep(500);
				
				jsonData = JSON.stringify({'action':'archive','type':'articles','csrf':document.querySelector('#csrf').dataset.csrf, 'slug':slug, 'status':state})
				//console.log(jsonData)
				
				// update data
				response = await fetch('/admin/engine/database', {
										method: 'POST',
									    body: jsonData,
									    headers: { 'Content-Type': 'application/json' }
									    }
									)
				data = await response.json();

				if (!response.ok) {
					notify("Action failed.", 'red')
					return
				}

				// Update DOM and table data
				btn.classList.toggle('archive')
				btn.classList.toggle('unarchive')
				statusCell.setValue(toarchive ? 'archived':'published')
				statusCell.getElement().classList.toggle(toarchive ? 'published':'archived')
				visualIcon.classList.toggle('view')
				visualIcon.classList.toggle('preview')
				modifyIcon.classList.toggle('edit')
				modifyIcon.classList.toggle('dynamic')

				notify('Statut mis à jour avec succès', 'green')
				
				activeTable.source = JSON.stringify(activeTable.table.getData())
				resetState(activeTable)
		}
		
		async function deleter(kwargs) {
				notify('Suppression en cours')
				
				// csrf token
				token = document.querySelector('#csrf').dataset.csrf;
				[event, cell] = kwargs;

				// dom objects
				btn = event.target.findParent('.icon')
				row = cell.getRow();

				// extract data
				type = activeTable.type;
				slug = type === 'articles' ? row.getData().url:row.getData().slug;
				value = cell.getValue();

				await sleep(500);
				
				jsonData = JSON.stringify({'action':'delete','type':type,'csrf':document.querySelector('#csrf').dataset.csrf, 'slug':slug, 'value':value})
				// console.log(jsonData)
				
				// update data
				response = await fetch('/admin/engine/database', {
										method: 'POST',
									    body: jsonData,
									    headers: { 'Content-Type': 'application/json' }
									    }
									)
				data = await response.json();
				// console.log(data)

				if (!response.ok) {
					notify("Action failed.", 'red')
					return
				}

				// Update DOM and table data
				row.delete()
				
				activeTable.source = JSON.stringify(activeTable.table.getData())
				resetState(activeTable)

				if (['tags', 'type'].includes(type)) {
					newData = await dataFetcher('articles');
					tableMatch.articles.source = JSON.stringify(newData['sql-data'])
					await resetTabledata(tableMatch.articles)
				}

				notify(type === 'articles' ? 'Article supprimé.':'Étiquette supprimée.')
		}

		// actions that require authentification
		document.querySelector('.overlay-form').onsubmit = e => {e.preventDefault();};
		async function saveCurrentData() {
				// active table
				table = activeTable.table;

				// select edited rows, with values different from original
				modifiedRows = Array.from(table.getEditedCells().concat(Object.values(autoEditedCells))).map(c => {return c.getValue() !== c.getInitialValue() ? c.getRow().getData():null}).filter((item, index, arr) => (item !== null && arr.indexOf(item) === index));

				// console.log(modifiedRows)

				// update fetch
				jsonData = JSON.stringify({'action':'update','type':activeTable.type,'csrf':document.querySelector('#csrf').dataset.csrf, 'data':modifiedRows})
				response = await fetch('/admin/engine/database', {
										method: 'POST',
									    body: jsonData,
									    headers: { 'Content-Type': 'application/json' }
									    }
									)
				
				data = await response.json();
				
				if (!response.ok) {
					// console.log(data.error);
					btn.disabled = false;
					btnbis.disabled = false;
					btn.innerHTML = 'Valider';
					return
				} else {
					tag.removeAttribute('invalid','');
					errorTag.removeAttribute('active');
					document.querySelector('#main .form-action-wrap').removeAttribute('active')
					tag.value = '';
					btn.disabled = false;
					btnbis.disabled = false;
					btn.innerHTML = 'Valider';
					
					activeTable.source = JSON.stringify(activeTable.table.getData())
					resetState(activeTable)

					if (activeTable.type === 'tags') {
						null
						// UPDATE TAGS MATCH TABLE
					}

					notify('Données mises à jour', 'green')
				}
				}
		
		// cancel requested action
		document.querySelector('#overlay-auth .action-btn.cancel').onclick = e => {
				document.querySelector("#overlay-auth").removeAttribute("active");
				document.querySelector("#overlay-pswd").value = '';
				pendingAction = {};
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
		document.querySelector('#overlay-auth .action-btn.validate').onclick = async e => {
				// check password
				validity = await passwordChecker();
				if (validity) {
					document.querySelector("#overlay-auth").removeAttribute("active");	
					pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
					pendingAction = {};
				}
		}

		// show panel to save changes
		document.querySelector('.action-btn.save').onclick = e => {
				pendingAction.function = saveCurrentData;
				document.querySelector("#overlay-auth").setAttribute("active",'');
		}

		// reset edits
		document.querySelector('.action-btn.reset').onclick = e => {
				pendingAction.function = resetTabledata;
				document.querySelector('#overlay-valid').toggleAttribute('active')
		}

		</script>
	</body>
</html>