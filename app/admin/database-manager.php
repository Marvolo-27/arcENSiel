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
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>	
				<h1>Bases de données</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les données"></div>
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

			<div class="actions-wrap data-actions linear">
				<div class='action-container'>
					<button class='action-btn responsive add-row'>Ajouter une ligne</button>
				</div>
				<div class='action-container'>
					<button class='action-btn responsive save'>Sauvegarder</button>
				</div>
				<div class='action-container'>
					<button class='action-btn responsive reset'>Réinitialiser</button>
				</div>
				<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
			</div>
		</div>
		
		<?php include($root . '/app/templates/overlay_authentification.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// on-load logic
		window.addEventListener('load', tablesBuilder)
		
		async function tablesBuilder() {
				// fetch all required data
				await fetchUsers('#users')
				await fetchArticles('#articles')
				await fetchTags('#tags')
				await fetchTypes('#types')
				await fetchCategories('#categories')
				
				// easy match table with their type
				tableMatch = {
							'users': {'table': usersTable, 'node': Table('users'), 'source': JSON.stringify(usersTable.getData()), 'call': fetchUsers, 'selector': '#users', 'type': 'users', 'appendable': false},
							'articles': {'table': articlesTable, 'node': Table('articles'), 'source': JSON.stringify(articlesTable.getData()), 'call': fetchArticles, 'selector': '#articles', 'type': 'articles', 'appendable': false},
							'tags': {'table': tagsTable, 'node': Table('tags'), 'source': JSON.stringify(tagsTable.getData()), 'call': fetchTags, 'selector': '#tags', 'type': 'tags', 'appendable': true},
							'types': {'table': typesTable, 'node': Table('types'), 'source': JSON.stringify(typesTable.getData()), 'call': fetchTypes, 'selector': '#types', 'type': 'types', 'appendable': true},
							'categories': {'table': categoriesTable, 'node': Table('categories'), 'source': JSON.stringify(categoriesTable.getData()), 'call': fetchCategories, 'selector': '#categories', 'type': 'categories', 'appendable': true}
				}
				activeTable = tableMatch[activeTable?.type] // Force active table update

				tablesListener(true)
				Block('main').section('data-actions').hide()				
		}

		async function archiveArticle(event, cell, toarchive) {				
				// dom objects
				const icon = event.target
				const row = cell.getRow()
				const statusCell = row.getCell('status')
				const iconsCellElement = row.getCell('actions').getElement()
				const visualIcon = iconsCellElement.icon('visualize')
				const modifyIcon = iconsCellElement.icon('modify')

				// Update icon
				icon.classList.toggle('archive')
				icon.classList.toggle('unarchive')

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

		function deleterTrigger(e, cell) {
				if (e.target.is('delete')) {
					pendingAction.function = deleter
					pendingAction.link = [e, cell]
					Overlay('auth').toggle()
				} else if (e.target.is('remove')) {
					activeTable.table.deleteRow(cell.getRow())
				}
		}

		async function deleter(args) {				
				const [event, cell] = args

				// dom objects
				const row = cell.getRow()
				const index = row.getPosition()
				const type = activeTable.type
				const slug = row.getData().url ?? row.getData().slug
				const value = cell.getValue()

				notify("Suppression en cours", `La suppression de '${slug}' de la liste des ${type} est en cours.`, 'orange')

				await sleep(500)
				
				// update data
				const jsonData = JSON.stringify({'action':'delete', 'type':type, 'csrf':csrfToken, 'slug':slug, 'value':value})
				const response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Erreur", `La suppression de '${slug}' de la liste des ${type} a échoué. Veuillez réessayer.`, 'red')
					return
				}
				
				// Update DOM and table data
				row.delete().then(() => tableMatch[activeTable.type].source = JSON.stringify(JSON.parse(tableMatch[activeTable.type].source).filter((item, i) => i !== index - 1)))
				
				// Update config Objects
				if (['tags', 'types'].includes(type)) {
					if (type === 'tags') {
						IDs = tableMatch.tags.table.getData().reduce((list, row) => [...list, row.id], [])
						Object.keys(tagsList).forEach(id => {if (!IDs.includes(parseInt(id))) delete tagsList[id]})
					} else if (type === 'types') {
						IDs = tableMatch.types.table.getData().reduce((list, row) => [...list, row.id], [])
						Object.keys(typesList).forEach(id => {if (!IDs.includes(parseInt(id))) delete typesList[id]})
					}

					// Update source data in articles
					const source = JSON.stringify(JSON.parse(tableMatch.articles.source).reduce((list, row) => {
						// Update type
						if (!Object.keys(typesList).includes(row.type.toString())) row.type = 0

						// Update tags
						row.tags = row.tags?.split(',').filter(tag => Object.keys(tagsList).includes(tag)).join(',') || null

						list.push(row)
						return list
					}, []))
					tableMatch.articles.source = source

					// Update corresponding cells
					tableMatch.articles.table.getColumn('type').getCells().forEach(cell => !Object.keys(typesList).includes(cell.getValue().toString()) ? cell.setValue(0):null)
					tableMatch.articles.table.getColumn('tags').getCells().forEach(cell => cell.setValue(cell.getValue()?.split(',').filter(tag => Object.keys(tagsList).includes(tag)).join(',') || null))
				}

				notify("Suppression effectuée", `'${slug}' a été supprimé de la liste des ${type}.`, 'green')
		}

		// actions that require authentification
		Overlay('auth').child('form').onsubmit = e => e.preventDefault()
		
		// refresh tables data
		Icon('refresh').onclick = databaseUpdater
		async function databaseUpdater(e) {
				e.target.show()
				notify("Synchronisation en cours",  "Les données sont en cours de synchronisation depuis la base de données.", 'orange')
				await tablesBuilder()
				notify("Synchronisation terminée", "Les données ont été mises à jour depuis la base de données.", 'green')
				e.target.hide()
		}

		// validate credentials and go on with requested action
		Overlay('auth').button('validate').onclick = async e => {
				validity = await passwordChecker()
				if (validity) transfer(e)
		}
		
		// cancel requested action
		Overlay('auth').button('cancel').onclick = e => {
				transfer(e, false)
				Overlay('auth').input('overlay-pswd').value = '';
		}
		
		// handle requested action
		Overlay('validate').button('validate').onclick = e => transfer(e)
		Overlay('validate').button('cancel').onclick = e => transfer(e, false)

		// Add row of data
		Button('add-row').onclick = e => {
			if (activeTable?.appendable) activeTable.table.addRow({'id': activeTable.table.getRows().slice(-1)[0].getData().id + 1, 'removable': true, 'slug': null, 'name': null, 'color': null, 'actions': true}).then(() => dataChecker(activeTable.table.getData()))
		}
		
		// show overlay save changes
		Button('save').onclick = e => {
				pendingAction.function = saveCurrentData
				Overlay('auth').show()
		}

		// show overlay to reset edits
		Button('reset').onclick = e => {
				pendingAction.function = resetTabledata
				Overlay('validate').show()
		}

		</script>
	</body>
</html>