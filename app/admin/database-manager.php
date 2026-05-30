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
		<title>Gestion de la base de données</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Gestion de la base de données">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		
		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
		
		<style>
		.inner-overlay .form-action-wrap {margin-top: 1em}


		.table .tabulator-cell.published {color: green; font-weight: bold; background: none !important;}
		.table .tabulator-cell.archived {color: red; font-weight: bold; background: none !important;}
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
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"><span class='img'></span></div>
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
					<span>
						<input class='checkbox' type='radio' id="categories-btn" name="table" value="categories">
						<label class='tag-item option blue' for="categories-btn">Sections</label>
					</span>
				</div>
			</div>

			<div class="tables">
				<div class='table' id='users'></div>
				<div class='table no-bound' id='articles'></div>
				<div class='table' id='tags'></div>
				<div class='table' id='types'></div>
				<div class='table' id='categories'></div>
			</div>

			<div class="form-action-wrap data-actions linear responsive">
				<div class='form-action'>
					<button class='action-btn save'>Sauvegarder</button>
				</div>
				<div class='form-action'>
					<button class='action-btn reset'>Réinitialiser</button>
				</div>
				<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
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
									Object.entries(typesMatchTable).forEach(([key, val]) => {if (parseInt(key) !== 0) typesCounter[key] = val.name})
									
									// fetch all required data
									await tablesBuilder()
									}
		
		async function tablesBuilder() {
				// fetch all required data
				await fetchUsers('#users');
				await fetchArticles('#articles');
				await fetchTags('#tags');
				await fetchTypes('#types');
				await fetchCategories('#categories');
				
				// easy match table with their type
				tableMatch = {
							'users': {'table': usersTable, 'source': JSON.stringify(usersTable.getData()), 'call': fetchUsers, 'selector': '#users', 'type': 'users'},
							'articles': {'table': articlesTable, 'source': JSON.stringify(articlesTable.getData()), 'call': fetchArticles, 'selector': '#articles', 'type': 'articles'},
							'tags': {'table': tagsTable, 'source': JSON.stringify(tagsTable.getData()), 'call': fetchTags, 'selector': '#tags', 'type': 'tags'},
							'types': {'table': typesTable, 'source': JSON.stringify(typesTable.getData()), 'call': fetchTypes, 'selector': '#types', 'type': 'types'},
							'categories': {'table': categoriesTable, 'source': JSON.stringify(categoriesTable.getData()), 'call': fetchCategories, 'selector': '#categories', 'type': 'categories'}
				}
				tablesListener(true)

				body.child('#main').child('.data-actions').hide()
		}

		async function archiveArticle(event, cell, toarchive) {				
				// dom objects
				const btn = event.target.findParent('.icon')
				const row = cell.getRow();
				const statusCell = row.getCell('status')
				const iconsCellElement = row.getCell('actions').getElement()
				const visualIcon = iconsCellElement.icon('visualize')
				const modifyIcon = iconsCellElement.icon('modify')

				// Update icon
				btn.classList.toggle('archive')
				btn.classList.toggle('unarchive')

				// extract data
				const state = toarchive ? 'archived':'published';
				const slug = row.getData().url;

				// enforce data update in source to avoid edit detection
				var tableData = JSON.parse(tableMatch.articles.source)
				tableData[articlesTable.getRowPosition(row) -1].status = state
				tableMatch.articles.source = JSON.stringify(tableData)
				
				notify("Mise à jour du statut d'un article", `${toarchive ? 'Archivage':'Publication'} de l'article ${slug}.`, 'orange')

				await sleep(500);
				
				// update data
				const jsonData = JSON.stringify({'action':'archive','type':'articles','csrf':csrfToken, 'slug':slug, 'status':state})
				const response = await fetchAPI('/admin/engine/database', jsonData)
				
				if (!response.ok) {
					notify("Erreur lors de la mise à jour du statut", `${toarchive ? "L'archivage":'La publication'} de l'article ${slug} a échoué. Veuillez réessayer.`, 'red')
					return
				}

				// Update DOM and table data
				statusCell.setValue(state)
				statusCell.getElement().classList.toggle(toarchive ? 'published':'archived')
				visualIcon.classList.toggle('view')
				visualIcon.classList.toggle('preview')
				modifyIcon.classList.toggle('edit')
				modifyIcon.classList.toggle('dynamic')

				notify("Succès de la mise à jour du statut", `L'article ${slug} a été ${toarchive ? 'archivé':'publié'} avec succès.`, 'green')
		}

		async function deleter(kwargs) {				
				[event, cell] = kwargs;

				// dom objects
				btn = event.target.findParent('.icon')
				row = cell.getRow();

				// extract data
				type = activeTable.type;
				slug = type === 'articles' ? row.getData().url:row.getData().slug;
				value = cell.getValue();

				notify("Suppression en cours", `La suppression de '${slug}' de la liste des ${type} est en cours.`, 'orange')

				await sleep(500);
				
				// update data
				jsonData = JSON.stringify({'action':'delete', 'type':type, 'csrf':csrfToken, 'slug':slug, 'value':value})
				response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Erreur", `La suppression de '${slug}' de la liste des ${type} a échoué. Veuillez réessayer.`, 'red')
					return
				}

				// Update DOM and table data
				row.delete()
				
				activeTable.source = JSON.stringify(activeTable.table.getData())
				resetState(activeTable)
				
				if (['tags', 'type'].includes(type)) {
					tableMatch.articles.source = await dataFetcher('articles').then(response => JSON.stringify(response));
					await resetTabledata(tableMatch.articles)
				}

				notify("Suppression effectuée", `'${slug}' a été supprimé de la liste des ${type}.`, 'green')
		}

		// actions that require authentification
		Overlay('auth').child('form').onsubmit = e => e.preventDefault();
		async function saveCurrentData() {
				// active table
				table = activeTable.table;

				// select edited rows, with values different from original
				modifiedRows = Array.from(table.getEditedCells().concat(Object.values(autoEditedCells))).map(c => {return c.getValue() !== c.getInitialValue() ? c.getRow().getData():null}).filterUnique();

				// update fetch
				jsonData = JSON.stringify({'action':'update', 'type':activeTable.type, 'csrf':csrfToken, 'data':modifiedRows})
				response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Échec de la mise à jour", `Des erreurs sont survenues lors de la mise à jour des données de la table '${activeTable.type}'. Consultez les logs pour plus d'informations.`, 'red')
					return
				} else {
					document.querySelector('#main').child('.form-action-wrap').hide()
					
					activeTable.source = JSON.stringify(activeTable.table.getData())
					resetState(activeTable)

					if (activeTable.type === 'tags') {
						null
						// UPDATE TAGS MATCH TABLE
					}

					notify("Succès", `Les données de la table '${activeTable.type}' ont été mises à jour.`, 'green')
				}
				}
		
		// refresh helloasso data
		Icon('refresh').onclick = async e => {
				icon = e.target.findParent('.icon')
				icon.show();
				notify("Synchronisation en cours",  `Les données sont en cours de synchronisation depuis la base de données.`, 'orange')
				await tablesBuilder()
				notify("Synchronisation terminée", "Les données ont été mises à jour depuis la base de données.", 'green')
				icon.hide()
		};

		// cancel requested action
		Overlay('auth').button('cancel').onclick = e => {
				e.container().hide();
				Overlay('auth').input('overlay-pswd').value = '';
				pendingAction = {};
		}

		// cancel requested action
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

		// validate credentials and go on with requested action
		Overlay('auth').button('validate').onclick = async e => {
				// check password
				validity = await passwordChecker();
				if (validity) {
					e.container().hide();	
					pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
					pendingAction = {};
				}
		}

		// show overlay save changes
		Button('save').onclick = e => {
				pendingAction.function = saveCurrentData;
				Overlay('auth').show();
		}

		// show overlay to reset edits
		Button('reset').onclick = e => {
				pendingAction.function = resetTabledata;
				Overlay('validate').show()
		}

		</script>
	</body>
</html>