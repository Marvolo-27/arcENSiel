<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

if ($_SESSION['role'] === 'admin') {
    $status = $status ?? 'view';
} else {
    $status = 'view';
}

// Section to display
$section ??= 'documents';

// Fetch categories of section
$query=$pdo->prepare("SELECT categories.slug AS slug, name, removable, sections.slug AS section FROM categories JOIN sections ON sections.id = section_id");
$query->execute();
$categories_raw = $query->fetchAll(PDO::FETCH_ASSOC);
$categories = array_filter($categories_raw, fn($item) => $item['section'] === $section);
file_put_contents($root . '/.temp/categories.json', json_encode($categories_raw));

// Fetch documents list
$query=$pdo->prepare("SELECT url, description, files.name AS name, status, size, categories.slug AS category FROM files JOIN files_categories ON files.id = file_id JOIN categories ON categories.id = category_id JOIN sections ON section_id = sections.id WHERE sections.slug = '".$section."'");
$query->execute();
$documents = $query->fetchAll(PDO::FETCH_ASSOC);

// Filter by categories
$files_list = array_reduce($categories, function($carry, $item) use ($documents) {
		if ($item['slug'] === 'statuts') return $carry; // skip statuts as specific structure
		$carry[$item['slug']] = [
			'name' => $item['name'],
			'removable' => $item['removable'],
			'documents' => array_filter($documents, function($doc) use ($item) {return $doc['category'] === $item['slug'];})
			] ;return $carry;}, []);

// Fetch latest statuts
$query=$pdo->prepare("SELECT url, size, description FROM files JOIN files_categories ON file_id = files.id JOIN categories ON category_id = categories.id WHERE status = TRUE AND categories.slug = 'statuts' ORDER BY updated_at DESC LIMIT 1;");
$query->execute();
$statuts = $query->fetchAll(PDO::FETCH_ASSOC)[0] ?? [];

// fetch last update
setlocale(LC_TIME, 'fr_FR.utf8');	
$statuts['date'] = json_decode(file_get_contents($server_content_file), true)['statuts']['date'] ?? '1900-01-01';
$statuts['date_fmt'] = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN, 'd MMMM YYYY')->format(DateTime::createFromFormat('Y-m-d', $statuts['date']))

?>

<!DOCTYPE html>
<html>
	<head>
		<title><?= $section === 'documents' ? 'Documents associatifs':'Ressources utiles' ?></title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<?php if ($status === 'edit'): ?><script src='/files/assets/js/upload.js'></script><?php endif; ?>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
            <h1>
                <?= $section === 'documents' ? 'Documents associatifs':'Ressources utiles' ?>
            </h1>

			<?php if($section === 'documents'): ?>
			<div>
				<h3>Statuts</h3>
				
				<?php if($status === 'edit'): ?>
				<div class='box statuts linear'>
					<div class='input-header'>
						<div class='icon info inline hoverable'>
							<span class='infotext'>Modifier la date de dernière mise à jour pour pouvoir charger les nouveaux statuts.</span>
						</div>
						
						<label class='label' for='art-url'>Dernière mise à jour</label>
					</div>
					<div class='input-container'>
						<input class='boxentry' type='date' name='update-time' id='update-time' data-source="<?= $statuts['date'] ?>" value="<?= $statuts['date'] ?>" autocomplete=off placeholder="Description" title="Date de mise à jour des statuts">
					</div>
				</div>
				<?php endif; ?>
				
				<?php if($status !== 'edit'): ?>
				<p>Dernière mise à jour : <?= $statuts['date_fmt'] ?></p>
				<?php endif; ?>

				<p>Consulter les <a id="statuts" href="<?= $statuts['url'] ?>" data-alias="<?= $statuts['description'] ?>" download="<?= end(explode('/', $statuts['url'])) ?>" target="_blank_">statuts</a> <small><i>(<?= formatBytes($statuts['size'],1) ?>)</i></small>.</p> 
				
				<?php if ($status === 'edit'): ?>
					<div class="actions-wrap linear left-align">
						<div class="action-container">
							<button class="action-btn new-file" data-category="statuts" data-mode="add" disabled>Mettre à jour les statuts</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="categories-wrap">
				<h3><?= $section === 'documents' ? 'Bilans et compte-rendus':'Ressources diverses' ?></h3>
				
				<?php foreach($files_list as $slug => $category) { ?>
					<div class="category-wrap">
						<p class="linear">
							<?php if ($status === 'edit' && $category['removable']): ?><span class="icon delete section inline dynamic hoverable"></span><?php endif; ?>
							<strong><?= $category['name'] ?></strong>
						</p>
						<ul class="<?= $slug ?>">
							<?php foreach($category['documents'] as $doc) { ?>
								<?php if ($doc['status']): ?>
								<li>
									<?php if ($status === 'edit'): ?>
										<div class='icon change delete inline dynamic hoverable' data-icon-type='delete' title="Supprimer cette entrée"></div>
										<div class='icon change edit inline dynamic hoverable' data-icon-type='edit' title="Modifier cette entrée"></div>
									<?php endif; ?>
									<a href="<?= $doc['url'] ?>" data-name="<?= $doc['name'] ?>" <?= $status === 'edit' ? "data-target-url='".$doc['url']."'":'' ?> download target="_blank_"><?= $doc['description'] ?></a> <small><i>(<?= formatBytes($doc['size'],1) ?>)</i></small>
								</li>
								<?php endif; ?>
							<?php } ?>
						</ul>
						<?php if ($status === 'edit'): ?>
							<div class="actions-wrap linear left-align">
								<div class="action-container">
									<button class="action-btn new-file" data-category="<?= $slug ?>" data-mode="add">Ajouter un fichier</button>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php } ?>
			</div>
			
			<?php if ($status === 'edit'): ?>
			<div>
				<div class="actions-wrap linear">
					<div class='action-container'>
						<button class='action-btn new-section'>Ajouter une section</button>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		
		<?php if($_SESSION['role'] === 'admin'): ?>
		<div id="admin-actions">
			<?php if($status === 'view'): ?>	
				<a class='icon edit inline large dynamic' href="/admin/tools/edit/<?= $section ?>" title="Modifier les fichiers"></a>
			<?php endif; ?>
			<?php if($status === 'edit'): ?>
				<a class='icon toggle-pass inline large dynamic hoverable' href="/<?= $section ?>" title="Retourner en mode spectacteur"></a>
			<?php endif; ?>
			<input name='csrf-token' id='csrf' data-csrf='<?= $_SESSION['CSRF'] ?>' value='<?= $_SESSION['CSRF'] ?>' required hidden readonly>
		</div>
		<?php endif; ?>

		<div class="body-overlay" id="overlay-deletion">
			<div class="inner-overlay">
				<div>
					<h4 class="title">
						Confirmation de suppression
					</h4>
					<span>Confirmer la suppression de : </span><span class="name"></span>
					<div class="activable"><small><it>Le fichier ne sera pas supprimé du serveur</it></small></div>
				</div>
				
				<div class='external actions-wrap linear'>
					<div class='action-container'>
						<button class='action-btn validate'>Poursuivre</button>
					</div>
					<div class='action-container'>
						<button class='action-btn cancel'>Annuler</button>
					</div>
				</div>
			</div>
		</div>
		
		<?php if ($status === 'edit'): ?>
			<?php include($root . '/app/templates/overlay_file-picker.html') ?>
			<?php include($root . '/app/templates/overlay_file-uploader.php') ?>
		<?php endif; ?>
        <?php include($root . '/app/templates/footer.html') ?>

		<script>
		<?php if ($status === 'edit'): ?>
			// global variables
			let category
			let mode
			let currentFile = {}	// {tag:tag, url:url}

		/// GENERAL ACTIONS
			// on load actions
			window.addEventListener('load', loader)
			async function loader()  {
										fileSelectorLoad('pdf')

										Overlay('filepicker').section('file-preview').toggle()
										Overlay('filepicker').button('fetch-all').toggle()
										}

			// trigger entry update
			Icons('change').forEach(icon => icon.onclick = e => changeEntryTrigger(e.target, e.target.dataset.iconType, e.target.parent('ul').className))
			function changeEntryTrigger(tag, action, type) {
						const overlay = Overlay('uploader')
						const item    = tag.parent('li').querySelector('a')
						const url     = item.getAttribute('href')		// .href gives absolute path, so not compatibe with DB format
						const name    = item.dataset.name
						
						currentFile = {'tag':item, 'url':url, 'category':type}
						
						if (action === 'delete') {// delete file from list							
							Overlay('deletion').child('.name').innerText = name;
							Overlay('deletion').child('.activable').show()
							Overlay('deletion').toggle()

						} else if (action === 'edit') {// edit pour changer description ou fichier
							overlay.child('.title').innerText = "Modification d'une entrée"
							overlay.child('.selected-file').innerText = name;

							overlay.input('desc').value = item.innerText;
							overlay.input('filename').dataset.name = url.filename(true)
							overlay.input('filename').value = url.filename()
							overlay.input('fileextension').value = url.extension()
							overlay.input('filename').readOnly = true
							overlay.input('file').required = false;
							
							overlay.section('frame').show()
							overlay.toggle()

							mode = 'update'
						}
					}
		
		<?php if($section === 'documents'): ?>
			// Statuts update switcher
			Input('update-time').onchange = e => e.target.value !== e.target.dataset.source ? Button('new-file[data-category="statuts"]').enable() : Button('new-file[data-category="statuts"]').disable()
		<?php endif; ?>

			// update file description on server
			async function descUpdate(form) {
						const formData  = new FormData(form)
						const sourceURL = currentFile.url ?? null
						const item = currentFile.tag ?? null
						currentFile = {}
						
						formData.set('mode', mode)
						formData.set('source', 'uploads/pdf/')
						formData.set('action', 'update')
						formData.set('section', 'documents')
						formData.set('category', category)
						formData.set('og-url', sourceURL)
						formData.set('url', item?.dataset.targetUrl)
						formData.set('filename', formData.get('filename') + '.' + Overlay('uploader').input('fileextension').value)
						formData.set('file-updated', mode === 'update' && sourceURL !== item?.dataset.targetUrl)
						formData.set('csrf', csrfToken)
						if (category === 'statuts') formData.set('date', Input('update-time').value)

						// Response object
						const response = await fetchAPI('/admin/engine/files', formData)
						const data = response.body

						if (!response.ok) {// http code 400-500 reuturn false
							notify("Échec de la requête", `La requête n'a pas pu être traitée sur le serveur. [Error HTTP ${response.httpcode}] ${response.body.error}`, 'red')
							return
						} else if (response.httpcode === 250) {// failed sql request
							errorData = data.error
							errorCode = data.code
							
							notify("Erreur lors de la mise à jour", `La mise à jour des informations a échoué. [Error ${errorCode}]: ${errorData}`, 'red')			
							return
						}

						notify("Mise à jour terminée", "Les informations ont été mises à jour.", 'green')
						
						if (mode === 'add') {
							if (category === 'statuts') {
								const time  = Input('update-time')
								const input = Input('statuts')
								Button('new-file[data-category="statuts"]').disable()
								
								time.dataset.source = time.value
								input.href = data.data.path
								input.download = data.data.name
								input.dataset.alias = data.data.desc
								input.nextElementSibling.firstChild.innerText = `(${formatBytes(data.data.size, 1)})`
							} else {
								li = document.createElement('li');
								li.innerHTML = `
										<div class='icon change delete inline dynamic hoverable'" data-icon-type='delete' title="Supprimer cette entrée"></div>
										<div class='icon change edit inline dynamic hoverable'" data-icon-type='edit' title="Modifier cette entrée"></div>
										<a href="${data.data.path}" data-name="${data.data.name}" target="_blank_" download>${data.data.desc}</a> <small><i>(${formatBytes(data.data.size, 1)})</i></small>
									`
								li.icons('change').forEach(icon => icon.onclick = e => changeEntryTrigger(e.target, e.target.dataset.iconType, e.target.parent('ul').className))
								Section(category).append(li)
							}
						} else {
							if (data.data.path != null) {// new file
								item.href = data.data.path;
								item.dataset.name = data.data.name;
								item.dataset.targetUrl = data.data.path;
								item.innerText = data.data.desc;
								item.nextElementSibling.firstElementChild.innerText = '('+formatBytes(data.data.size, 1)+')'
							} else {
								item.innerText = data.data.decsription;
							}
						}
						item.value = '';
						fileSelectorLoad('pdf')
					}

			// delete entry on server
			async function removeElement() {
						const url = currentFile.url
						const tag = currentFile.tag
						currentFile = {}

						// update data on server
						const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'reference', 'mode':'delete', 'url':url, 'category':category})
						const response = await fetchAPI('/admin/engine/files', jsonData)

						if (!response.ok) {
							notify("Échec lors de la suppression", `Le fichier ${url.filename()} n'a pas pu être supprimé de la liste des ${category}`, 'red')
						} else {
							notify("Document retiré", `Le fichier ${url.filename()} a été supprimé de la liste des ${category}`, 'green')
							tag.parentNode.remove()
						}
					}

			function fileSelectorFilter(category) {
					container = Overlay('filepicker').section('files-list-container')

					Array.from(container.children).forEach(wrap => {
						wrap.child('.file-element').dataset.references?.split(',').includes(category) ? wrap.set('hidden'):wrap.unset('hidden')
					})
				}

			function newEntry(e) {
					overlay = Overlay('uploader')
					overlay.child('form').reset()
					overlay.child('.fileentry').required = true
					overlay.section('frame').hide()
					overlay.child('.title').innerText = "Ajout d'une entrée"
					overlay.toggle()

					category = e.target.dataset.category
					mode = e.target.dataset.mode
				}

			// show panel to save changes
			Buttons('new-file').forEach(button => button.onclick = newEntry)

			Overlay('deletion').button('cancel').onclick = e => {
				Overlay('deletion').hide()
				currentFile = {}
			}

			Overlay('deletion').button('validate').onclick = e => {
				Overlay('deletion').hide()
				Overlay('deletion').child('.activable').is('active') ? removeElement() : removeSection()
			}

			// filename input
			Overlay('uploader').input('filename').oninput = e => {
					pos = e.target.selectionStart
					e.target.value = e.target.value.filenameForm()
					e.target.setSelectionRange(pos,pos)
				}
			
			Overlay('uploader').icon('delete').onclick = resetFileUpload
			Overlay('uploader').button('cancel').onclick = e => {
				resetFileUpload(e, true)
				currentFile = {}
			}

			Overlay('uploader').child('form').onsubmit = e => {
				e.preventDefault()
				Overlay('uploader').hide()
				descUpdate(e.target)
			}
			
			// show file selection panel
			Overlay('uploader').button('exist-file').onclick = e => {
					fileSelectorFilter(category)
					
					Overlay('uploader').toggle()
					Overlay('filepicker').toggle()
			}
			
			// cancel request action
			Overlay('filepicker').button('cancel').onclick = e => {
				Overlay('filepicker').hide()
				Overlay('uploader').show()
				Overlay('filepicker').childs('.file-element').forEach(tag => tag.unset('selected'))
				Overlay('uploader').input('filename').readOnly = false
				//currentFile = {};
			}

			Overlay('filepicker').button('validate').onclick = e => {
					const [url, tag] = filePickManage()

					if (url) {
						Overlay('uploader').child('.selected-file').innerText = url.filename(true)
						Object.keys(currentFile).length !== 0 ? currentFile.tag.dataset.targetUrl = url:currentFile = {'url': null, 'tag': tag}	// prevent error on add mode
					}
			}

		// FILE MANAGEMENT //
			Overlay('filepicker').button('fetch-all').onclick = async e => {
					tag = e.target.parent('button')
					
					tag.toggle()
					tag.disabled = true
					await fileSelectorLoad()
					fileSelectorFilter(category)
					tag.toggle()
			}
			
		/// SECTION MANAGEMENT
			Button('new-section').onclick = e => {
				e.target.disable()
				const container = Block('main').section('categories-wrap')
				
				const editor = document.createElement('p')
				editor.innerHTML = `<div class='box section linear condensed'>
										<div class='icon validity dynamic inline hoverable'></div>
										<div class='input-container'>
											<strong class="editable" id="new-section" contenteditable placeholder='Section' empty></strong>
											<input name='section' id="new-category" type="text" required hidden>
										</div>
									</div>`
				container.append(editor)

				Input('new-section').oninput = e => {
					tag = e.target
					tag.innerText.strForm() === '' ? tag.set('empty'):tag.unset('empty')
					tag.nextElementSibling.value = tag.textContent
				}

				Box('section').icon('validity').onclick = async e => {
					const box = e.target.parent('box')
					const value = box.input('new-category').value

					if (!value) {
						notify("Saisie vide", "Veuillez entrer un nom de section avant de valider.", 'red')
						return
					}
					
					if (categoriesList.includes(value)) {
						notify("Duplicat", "L'identifiant pour cette section existe déjà.", 'red')
						return
					}

					notify("Ajout", "La nouvelle section est en cours de création.", 'orange')

					const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'insert', 'type':'categories', 'section':'documents', 'slug':value.urlForm(), 'name':value})
					const response = await fetchAPI('/admin/engine/database', jsonData)

					if (!response.ok) {
						notify("Échec de l'ajout", `L'ajout de la section '${value.urlForm()}' a échoué. Veuillez réessayer.`, 'red')
						return
					}
					
					Box('section').parentNode.remove()
					Button('new-section').enable()

					const wrap = document.createElement('div')
					wrap.innerHTML =   `<p class="linear">
											<span class="icon delete section inline dynamic hoverable"></span>
											<strong>${value}</strong> 
										</p>
										<ul class="${value.urlForm()}">
										</ul>
										<div class="actions-wrap linear left-align">
											<div class="action-container">
												<button class="action-btn new-file" data-category="<?= $slug ?>" data-mode="add">Ajouter un fichier</button>
											</div>
										</div>`
					wrap.classList.toggle('category-wrap')
					wrap.button('new-file').onclick = newEntry
					wrap.icon('delete.section').onclick = deleteSectionTrigger
					
					Section('categories-wrap').append(wrap)
					notify("Section ajoutée", "La nouvelle section a été créée.", 'green')
				}
			}
		
		Icons('delete.section')?.forEach(icon => icon.onclick = deleteSectionTrigger)
		
		async function deleteSectionTrigger(e) {
				const container = e.target.parent('.category-wrap')
				const category  = container.button('new-file').dataset.category
				const name = container.child('strong').textContent

				currentFile = {'url': category, 'tag': container}
				Overlay('deletion').child('.name').innerText = name
				Overlay('deletion').child('.activable').hide()
				Overlay('deletion').toggle()
		}

		async function removeSection() {
				const value = currentFile.url
				const section = currentFile.tag
				currentFile = {}

				notify("Suppression", "La section est en cours de suppression.", 'orange')

				const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'delete', 'type':'categories', 'slug':value})
				const response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Échec de la suppression", `La suppression de la section '${value.urlForm()}' a échoué. Veuillez réessayer.`, 'red')
					return
				}

				section.remove()
				notify("Suppression terminée", `La section '${value.urlForm()}' a été supprimée.`, 'green')
			}
		<?php endif; ?>
		</script>
    </body>
</html>

