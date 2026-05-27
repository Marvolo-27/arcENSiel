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
$query=$pdo->prepare("SELECT categories.slug AS slug, name, sections.slug AS section FROM categories JOIN sections ON sections.id = section_id");
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
			'documents' => array_filter($documents, function($doc) use ($item) {return $doc['category'] === $item['slug'];})
			] ;return $carry;}, []);

// Fetch latest statuts
$query=$pdo->prepare("SELECT url, size, description FROM files JOIN files_categories ON file_id = files.id JOIN categories ON category_id = categories.id WHERE status = TRUE AND categories.slug = 'statuts' ORDER BY updated_at DESC LIMIT 1;");
$query->execute();
$statuts = $query->fetchAll(PDO::FETCH_ASSOC)[0] ?? [];

// fetch last update
setlocale(LC_TIME, 'fr_FR.utf8');	
$statuts['date'] = json_decode(file_get_contents($server_content_file), true)['statuts']['date'];
$statuts['date_fmt'] = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN, 'd MMMM YYYY')->format(DateTime::createFromFormat('Y-m-d', $statuts['date']))

?>

<!DOCTYPE html>
<html>
	<head>
		<title>Documents associatifs</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/upload.js'></script>

		<style>
			.left-align {justify-content: flex-start;}

			li .icon {display: inline-block;}

			#filename, #fileextension {font-size: .7em}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
            <h1>
                Documents associatifs
            </h1>

			<?php if($section === 'documents'): ?>
			<div>
				<h3>Statuts</h3>
				
				<p>Dernière mise à jour : 
					<span class="last-updated">
						<?php if ($status === 'edit'): ?>
							<input class='boxentry' type='date' name='update-time' id='update-time' data-source="<?= $statuts['date'] ?>" value="<?= $statuts['date'] ?>" autocomplete=off placeholder="Description" title="Date de mise à jour des statuts" required>
						<?php endif; ?>
						<?php if ($status !== 'edit'): ?>
							<?= $statuts['date_fmt'] ?>
						<?php endif; ?>
					</span>
				</p>
				
				<p>Consulter les <a id="statuts" href="<?= $statuts['url'] ?>" data-alias="<?= $statuts['description'] ?>" download="<?= end(explode('/', $statuts['url'])) ?>" target="_blank_">statuts</a> <small><i>(<?= formatBytes($statuts['size'],1) ?>)</i></small>.</p> 
				
				<?php if ($status === 'edit'): ?>
					<div class="form-action-wrap linear left-align">
						<div class="form-action">
							<button class="action-btn new-file" data-category="statuts" data-mode="add" disabled>Mettre à jour les statuts</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="categories-wrap">
				<h3>Bilans et compte-rendus</h3>
				
				<?php foreach($files_list as $slug => $category) { ?>
					<p><strong><?= $category['name'] ?></strong> 
						<ul class="<?= $slug ?>">
							<?php foreach($category['documents'] as $doc) { ?>
								<?php if ($doc['status']): ?>
								<li>
									<?php if ($status === 'edit'): ?>
										<div class='icon change delete inline medium dynamic hoverable' data-icon-type='delete' title="Supprimer cette entrée"><span class='img'></span></div>
										<div class='icon change edit inline medium dynamic hoverable' data-icon-type='edit' title="Modifier cette entrée"><span class='img'></span></div>
									<?php endif; ?>
									<a href="<?= $doc['url'] ?>" data-name="<?= $doc['name'] ?>" <?= $status === 'edit' ? "data-target-url='".$doc['url']."'":'' ?> download target="_blank_"><?= $doc['description'] ?></a> <small><i>(<?= formatBytes($doc['size'],1) ?>)</i></small>
								</li>
								<?php endif; ?>
							<?php } ?>
						</ul>
						<?php if ($status === 'edit'): ?>
							<div class="form-action-wrap linear left-align">
								<div class="form-action">
									<button class="action-btn new-file" data-category="<?= $slug ?>" data-mode="add">Ajouter un fichier</button>
								</div>
							</div>
						<?php endif; ?>
					</p>
				<?php } ?>
			</div>
			
			<?php if ($status === 'edit'): ?>
			<div>
				<div class="form-action-wrap linear">
					<div class='form-action'>
						<button class='action-btn new-section'>Ajouter une section</button>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		
		<?php if($_SESSION['role'] === 'admin'): ?>
		<div id="admin-actions">
			<?php if($status === 'view'): ?>	
			<a class='icon edit inline large dynamic' href="/admin/tools/edit/documents" title="Modifier les fichiers">
				<span class='img'></span>
			</a>
			<?php endif; ?>
			<?php if($status === 'edit'): ?>
			<a class='icon toggle-pass inline large dynamic hoverable' href="/documents" title="Retourner en mode spectacteur">
				<span class='img'></span>
			</a>
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
					<span>Confirmer la suppression de : </span><span class="filename"></span>
					<div><small><it>Le fichier ne sera pas supprimé du serveur</it></small></div>
				</div>
				
				<div class='external form-action-wrap linear'>
					<div class='form-action'>
						<button class='action-btn validate'>Poursuivre</button>
					</div>
					<div class='form-action'>
						<button class='action-btn cancel'>Annuler</button>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/overlay_file-picker.html') ?>
		<?php include($root . '/app/templates/overlay_file-uploader.html') ?>
        <?php include($root . '/app/templates/footer.html') ?>

		<script>
		<?php if ($status === 'edit'): ?>
			// global variables
			let category;
			let mode;
			let currentFile = {}; // {tag:tag, url:url}

		/// GENERAL ACTIONS
			// on load actions
			window.addEventListener('load', loader)
			async function loader()  {
										fileSelectorLoad('pdf')

										Overlay('filepicker').querySelector('.file-preview').toggle()
										Overlay('filepicker').querySelector('.fetch-all').toggle()
										}

			// trigger entry update
			document.body.childs('.change').forEach(icon => icon.onclick = e => {t = e.target.findParent('.icon'); changeEntryTrigger(t, t.dataset.iconType, t.findParent('ul').className)})
			function changeEntryTrigger(tag, action, type) {
						linkTag = tag.findParent('li').querySelector('a');
						url = linkTag.getAttribute('href'); // .href gives absolute path, so not compatibe with DB format
						name = linkTag.dataset.name
						category = type;
						currentFile = {'tag':linkTag, 'url':url, 'category':type}
						
						if (action === 'delete') {// delete file from list							
							Overlay('deletion').child('.filename').innerText = name;
							Overlay('deletion').toggle()

						} else if (action === 'edit') {// edit pour changer description ou fichier
							overlay = Overlay('uploader')
							overlay.box('description').input().value = linkTag.innerText;
							overlay.child('.title').innerText = "Modification d'une entrée"
							overlay.child('.selected-file').innerText = name;
							overlay.child('.frame').show()
							overlay.child('.fileentry').required = false;
							overlay.toggle()

							overlay.input('filename').dataset.name = url.filename(true)
							overlay.input('filename').value = url.filename()
							overlay.input('fileextension').value = url.extension();
							overlay.input('filename').readOnly = true
							
							mode = 'update';
						}

						return
					}
			
			// Statuts update switcher
			Input('update-time').onchange = e => {
				e.target.value !== e.target.dataset.source ? Button('new-file[data-category="statuts"]').enable() : Button('new-file[data-category="statuts"]').disable();
			}


			// on drop logic
			function fileDropHandle(e) {
						allowDrop(e);
						
						data = e.dataTransfer;
						files = data.files;
						
						input = Input('file');
						input.files = files;
						
						upload = fileUploadManage(input, /(application\/(pdf|zip|vnd\.sqlite3)|image\/.*)/, 10)
						
						if (upload) {
							Overlay('uploader').input('filename').value = file.name.filename().filenameForm()
							Overlay('uploader').input('filename').dataset.name = file.name.filenameForm()
							Overlay('uploader').input('fileextension').value = file.name.extension();
						}
					}

			// update file description on server
			async function descUpdate(form) {
						ogUrl = currentFile.url ?? null
						tag = currentFile.tag ?? null
						currentFile = {};

						fileTag = Overlay('uploader').child('.fileentry')

						formData = new FormData(form);
						formData.set('mode', mode)
						formData.set('source', 'uploads/pdf/')
						formData.set('action', 'update')
						formData.set('section', 'documents')
						formData.set('category', category)
						category === 'statuts' ? formData.set('date', Input('update-time').value) : null
						formData.set('og-url', ogUrl)
						formData.set('url', tag?.dataset.targetUrl)
						formData.set('filename', formData.get('filename') + '.' + Overlay('uploader').input('fileextension').value)
						formData.set('file-updated', mode === 'update' && ogUrl !== tag?.dataset.targetUrl)
						formData.set('csrf', csrfToken)

						// Response object
						response = await fetchAPI('/admin/engine/files', formData)
						data = response.body

						if (!response.ok) {// http code 400-500 reuturn false
								alert(`Error: HTTP ${response.httpcode}: ${response.body.error}`);
								return null
							}
						
						if (response.httpcode === 250) {// failed sql request
							errorData = data.error;
							errorCode = data.code;
							
							notify("Erreur lors de la mise à jour", `La mise à jour des informations a échoué. [Error ${errorCode}]: ${errorData}`, 'red');							
							return
						} else {// correct sql response
							notify("Mise à jour terminée", "Les informations ont été mises à jour.", 'green')
						}

						if (mode === 'add') {
							if (category === 'statuts') {
								Input('update-time').dataset.source = Input('update-time').value
								Button('new-file[data-category="statuts"]').disable()

								Input('statuts').href = data.data.path
								Input('statuts').download = data.data.name
								Input('statuts').dataset.alias = data.data.desc
								Input('statuts').nextElementSibling.firstChild.innerText = `(${formatBytes(data.data.size, 1)})`
							} else {
								li = document.createElement('li');
								li.innerHTML = `
										<div class='icon change delete inline medium dynamic hoverable'" data-icon-type='delete' title="Supprimer cette entrée"><span class='img'></span></div>
										<div class='icon change edit inline medium dynamic hoverable'" data-icon-type='edit' title="Modifier cette entrée"><span class='img'></span></div>
										<a href="${data.data.path}" data-name="${data.data.name}" target="_blank_" download>${data.data.desc}</a> <small><i>(${formatBytes(data.data.size, 1)})</i></small>
									`
								li.childs('.icon.change').forEach(icon => icon.onclick = e => {t = e.target.findParent('.icon'); changeEntryTrigger(t, t.dataset.iconType, t.findParent('ul').className)})
								document.querySelector(`.${category}`).append(li)
							}
						} else {
							if (data.data.path != null) {// new file
								tag.href = data.data.path;
								tag.dataset.name = data.data.name;
								tag.dataset.targetUrl = data.data.path;
								tag.innerText = data.data.desc;
								tag.nextElementSibling.firstElementChild.innerText = '('+formatBytes(data.data.size, 1)+')'
							} else {
								tag.innerText = data.data.decsription;
							}
						}
						fileTag.value = '';
					}

			// delete entry on server
			async function removeElement() {
						url = currentFile.url;
						tag = currentFile.tag;
						currentFile = {};

						// update data on server
						jsonData = JSON.stringify({'csrf':csrfToken, 'action':'reference', 'mode':'delete', 'url':url, 'category':category})
						response = await fetchAPI('/admin/engine/files', jsonData)

						if (response.ok) {
							notify("Document retiré", `Le fichier ${url.filename()} a été supprimé de la liste des ${category}`, 'green');
							tag.parentNode.remove();
						}
					}

			// filename input
			Overlay('uploader').input('filename').oninput = e => {
					pos = e.target.selectionStart
					e.target.value = e.target.value.filenameForm()
					e.target.setSelectionRange(pos,pos)
				}

			// show panel to save changes
			document.body.childs('.action-btn.new-file').forEach(button => button.onclick = function(e){
				overlay = Overlay('uploader')
				overlay.child('form').reset();
				overlay.child('.fileentry').required = true;
				overlay.child('.frame').hide();
				overlay.child('.title').innerText = "Ajout d'une entrée"
				overlay.toggle()

				category = e.target.dataset.category
				mode = e.target.dataset.mode
			})

			Overlay('uploader').button('cancel').onclick = e => {
				overlay = e.container()
				overlay.hide();
				overlay.child('form').reset();
				overlay.child('.selected-file').innerText = '';
				overlay.input('filename').readOnly = false
				currentFile = {};
			}

			Overlay('deletion').button('cancel').onclick = e => {
				e.container().hide();
				currentFile = {};
			}

			Overlay('deletion').button('validate').onclick = e => {
				e.container().hide();
				removeElement()
			}

			Overlay('uploader').child('form').onsubmit = e => {
				e.preventDefault();
				e.container().hide();
				
				descUpdate(e.target)
			}

			Overlay('uploader').icon('delete').onclick = e => {
				overlay = Overlay('uploader')
				filename = overlay.input('filename')
				fileextension = overlay.input('fileextension')
				file = overlay.input('file')

				overlay.child('.frame').hide();
				overlay.child('.selected-file').innerText = '';
				file.required = true;
				file.value = '';

				filename.dataset.name = ''
				filename.value = ''
				filename.readOnly = false
				fileextension.value = ''
			}

			// cancel request action
			Overlay('filepicker').button('cancel').onclick = e => {
				e.container().toggle();
				Overlay('uploader').toggle();
				e.container().childs('.file-element').forEach(tag => {tag.unset('selected')})
				Overlay('uploader').input('filename').readOnly = false
				//currentFile = {};
			}

			// show file selection panel
			Overlay('uploader').button('exist-file').onclick = e => {
					Overlay('uploader').toggle();
					Overlay('filepicker').toggle();
			}

			Overlay('filepicker').button('validate').onclick = e => {
					tag = Overlay('filepicker').child('.file-element[selected]')
					error = Error('file-choice')

					if (tag === null) {
						error.innerText = 'Sélectionnez un fichier pour continuer';
						error.show();
					} else {
						overlay = Overlay('uploader')
						error.hide();
						e.container().hide();
						overlay.toggle();

						url = tag.dataset.targetUrl;
						Object.keys(currentFile).length !== 0 ? currentFile.tag.dataset.targetUrl = url:currentFile = {'url': null, 'tag': tag}; // prevent error on add mode
						
						overlay.input('filename').dataset.name = url.filename(true)
						overlay.input('filename').value = url.filename()
						overlay.input('fileextension').value = url.extension()
						overlay.input('desc').value = tag.dataset.alt
						overlay.input('filename').readOnly = true

						e.container().childs('.file-element').forEach(tag => {tag.unset('selected')})
						overlay.child('.selected-file').innerText = url.filename(true);
						overlay.child('.frame').show();
						overlay.child('.fileentry').required = false;
					}
			}

			// FILE MANAGEMENT //
		Input('file').onchange = e => {
							upload = fileUploadManage(e.target, /(application\/(pdf|zip|vnd\.sqlite3)|image\/.*)/, 10)
							if (upload) {
								Overlay('uploader').input('filename').value = file.name.filename().filenameForm()
								Overlay('uploader').input('filename').dataset.name = file.name.filenameForm();
								Overlay('uploader').input('fileextension').value = file.name.extension();
							}
							};

		async function fileSelectorLoad(type=null) {
				container = Overlay('filepicker').child('.files-list-container');
				container.innerHTML = '<div class="spinner"></div>';

				jsonData = JSON.stringify({'csrf':csrfToken, 'action':'fetch', 'filetype':type})
				response = await fetchAPI('/admin/engine/files', jsonData)
				
				container.innerHTML = '';

				response.body.forEach(file => {
					wrap = document.createElement('div')
					wrap.classList.toggle('file-element-wrap')

					div = document.createElement('div');

					div.dataset.targetUrl = file.url
					div.dataset.alt = file.description
					div.innerText = file.name
					div.classList.toggle('file-element')
					div.addEventListener('click', fileUpdateSelection)

					wrap.append(div)
					container.append(wrap)
				})

		}

		function fileUpdateSelection(event) {
				Overlay('filepicker').childs('.file-element').forEach(tag => {tag.unset('selected')})
				target = event.target;

				target.set('selected')
		}

		Overlay('filepicker').button('fetch-all').onclick = async e => {
				tag = e.target.findParent('.action-btn')
				
				tag.toggle()
				tag.disabled = true
				await fileSelectorLoad()
				tag.toggle()
		}
		
	/// SECTION MANAGEMENT
		Button('new-section').onclick = e => {
			e.target.disable()
			container = body.child('#main').child('.categories-wrap')
			
			wrap = document.createElement('p')
			wrap.innerHTML = `	<div class='box section linear condensed'>
									<div class='icon validity medium dynamic inline hoverable'><span class='img'></span></div>
									<div class='input-container'>
										<strong class="editable" id="new-section" contenteditable placeholder='Section' empty></strong>
										<input name='section' type="text" required hidden>
									</div>
								</div>`
			container.append(wrap)

			Input('new-section').oninput = e => {
				tag = e.target;
				tag.innerText === '\n' || tag.innerText === '' ? tag.set('empty'):tag.unset('empty')
				tag.nextElementSibling.value = tag.textContent;
			}

			Box('section').icon('validity').onclick = async e => {
				box = e.container()
				value = box.input().value

				if (!value) {
					notify("Saisie vide", "Veuillez entrer un nom de section avant de valider.", 'red')
					return
				}
				
				if (categoriesList.includes(value)) {
					notify("Duplicat", "L'identifiant pour cette section existe déjà.", 'red')
					return
				}

				notify("Ajout", "La nouvelle section est en cours de création.", 'orange')

				jsonData = JSON.stringify({'csrf':csrfToken, 'action':'insert', 'type':'categories', 'section':'documents', 'slug':value.urlForm(), 'name':value})
				response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Échec de l'ajout", `L'ajout de la section '${value.urlForm()}' a échoué. Veuillez réessayer.`, 'red')
					return
				}
				
				Box('section').remove()

				wrap = document.createElement('p')
				wrap.innerHTML =   `<strong>${value}</strong> 
									<ul class="${value.urlForm()}">
									</ul>
									<div class="form-action-wrap linear left-align">
										<div class="form-action">
											<button class="action-btn new-file" data-category="<?= $slug ?>" data-mode="add">Ajouter un fichier</button>
										</div>
									</div>`
				
				wrap.button('new-file').onclick = function(e){
						overlay = Overlay('uploader')
						overlay.child('form').reset();
						overlay.child('.fileentry').required = true;
						overlay.child('.frame').hide();
						overlay.child('.title').innerText = "Ajout d'une entrée"
						overlay.toggle()

						category = e.target.dataset.category
						mode = e.target.dataset.mode
						}
				
				document.querySelector('.categories-wrap').append(wrap)

				notify("Section ajoutée", "La nouvelle section a été créée.", 'green')
			}
		}
		<?php endif; ?>
		</script>
    </body>
</html>

