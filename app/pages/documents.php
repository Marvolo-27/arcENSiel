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

// Fetch documents list
$query=$pdo->prepare("SELECT url, description, name, status, size, category, subcategory FROM files WHERE used_by LIKE '%documents%';");
$query->execute();
$documents = $query->fetchAll(PDO::FETCH_ASSOC) ?? null;

// Fetch latest statuts
$query=$pdo->prepare("SELECT url, size, description FROM files WHERE status = TRUE AND category = 'statuts' AND used_by LIKE '%documents%' ORDER BY updated_at DESC LIMIT 1;");
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

			 <h3>
                Statuts
            </h3>
                <p>Dernière mise à jour : 
					<span class="last-updated">
						<?php if ($status === 'edit'): ?>
							<input class='boxentry' type='date' name='update-time' id='update-time' value="<?= $statuts['date'] ?>" autocomplete=off placeholder="Description" title="Date de mise à jour des statuts" required>
						<?php endif; ?>
						<?php if ($status !== 'edit'): ?>
							<?= $statuts['date_fmt'] ?>
						<?php endif; ?>
					</span>
				</p>

                <p>Consulter les <a href="<?= $statuts['url'] ?>" target="_blank_" data-alias="<?= $statuts['description'] ?>" download="<?= end(explode('/', $statuts['url'])) ?>">statuts</a> <small><i>(<?= formatBytes($statuts['size'],1) ?>)</i></small>.</p> 
	
				<?php if ($status === 'edit'): ?>
					<div class='icon edit inline medium dynamic hoverable' onclick="updateStatutsTrigger(event.target)" title="Modifier cette entrée"><span class='img'></span></div>
				<?php endif; ?>
			<h3>
                Bilans annuels
            </h3>   
                <p><strong>Bilans moraux :</strong> 
                    <ul class="bm">
                        <?php foreach(array_reverse($documents) as $doc) { ?>
							<?php if ($doc['status'] && $doc['category'] === 'bilan' && $doc['subcategory'] === 'BM'): ?>
								<li>
									<?php if ($status === 'edit'): ?>
										<div class='icon delete inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'delete', 'bm')" title="Supprimer cette entrée"><span class='img'></span></div>
										<div class='icon edit inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'edit', 'bm')" title="Modifier cette entrée"><span class='img'></span></div>
									<?php endif; ?>
									<a href="<?= $doc['url'] ?>" data-name="<?= $doc['name'] ?>" <?= $status === 'edit' ? "data-target-url='".$doc['url']."'":'' ?> target="_blank_" download><?= $doc['description'] ?></a> <small><i>(<?= formatBytes($doc['size'],1) ?>)</i></small>
								</li>
							<?php endif; ?>
						<?php } ?>
                    </ul>
					<?php if ($status === 'edit'): ?>
						<div class="form-action-wrap linear left-align">
							<div class='form-action'>
								<button class='action-btn new-bm'>Ajouter un fichier</button>
							</div>
						</div>
					<?php endif; ?>
                </p>

				<p><strong>Bilans financiers :</strong> 
                    <ul class="bf">
                        <?php foreach(array_reverse($documents) as $doc) { ?>
							<?php if ($doc['status'] && $doc['category'] === 'bilan' && $doc['subcategory'] === 'BF'): ?>
								<li>
									<?php if ($status === 'edit'): ?>
										<div class='icon delete inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'delete', 'bf')" title="Supprimer cette entrée"><span class='img'></span></div>
										<div class='icon edit inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'edit', 'bf')" title="Modifier cette entrée"><span class='img'></span></div>
									<?php endif; ?>
									<a href="<?= $doc['url'] ?>" data-name="<?= $doc['name'] ?>" <?= $status === 'edit' ? "data-target-url='".$doc['url']."'":'' ?> target="_blank_" download><?= $doc['description'] ?></a> <small><i>(<?= formatBytes($doc['size'],1) ?>)</i></small>
								</li>
							<?php endif; ?>
						<?php } ?>
                    </ul>

					<?php if ($status === 'edit'): ?>
						<div class="form-action-wrap linear left-align">
							<div class='form-action'>
								<button class='action-btn new-bf'>Ajouter un fichier</button>
							</div>
						</div>
						<input name='csrf-token' id='csrf' data-csrf='<?= $_SESSION['CSRF'] ?>' value='<?= $_SESSION['CSRF'] ?>' required hidden readonly>
					<?php endif; ?>
                </p>
        </div>
        
		<?php if($_SESSION['role'] === 'admin'): ?>
		<div id="admin-actions">
			<?php if($status === 'view'): ?>	
			<a class='icon edit inline large dynamic' href="/admin/tools/editor/documents" title="Modifier les fichiers">
				<span class='img'></span>
			</a>
			<?php endif; ?>
			<?php if($status === 'edit'): ?>
			<a class='icon toggle-pass inline large dynamic hoverable' href="/documents" title="Retourner en mode spectacteur">
				<span class='img'></span>
			</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="body-overlay" id="overlay-valid">
			<div class="inner-overlay">
				<div>
					<h4 class="title">
						Confirmation de suppression
					</h4>
					<span>Confirmer la suppression de :</span><span class="filename"></span>
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
			let subcategory;
			let mode;
			let currentFile = {}; // {tag:tag, url:url}
			
			const overlayAdd = document.querySelector('#overlay-add')
			const overlayAuth = document.querySelector('#overlay-auth')
			const overlayChoose = document.querySelector('#overlay-choose')
			const overlayValid = document.querySelector('#overlay-valid')

		/// GENERAL ACTIONS
			// on load actions
			window.addEventListener('load', loader)
			async function loader()  {
										fileSelectorLoad('bilan')

										overlayChoose.querySelector('.file-preview').toggle()
										overlayChoose.querySelector('.fetch-all').toggle()
										}

			// trigger entry update
			function changeEntryTrigger(tag, action, subcat = 'bm') {
						linkTag = tag.findParent('li').querySelector('a');
						url = linkTag.getAttribute('href'); // .href gives absolute path, so not compatibe with DB format
						name = linkTag.dataset.name
						subcategory = subcat;
						currentFile = {'tag': linkTag, 'url':url, 'subcat': subcat}
						
						if (action === 'delete') {// delete file from list							
							overlayValid.child('.filename').innerText = name;
							overlayValid.toggle()

						} else if (action === 'edit') {// edit pour changer description ou fichier
							overlay = Overlay('ulpoader')
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
			
			// trigger status update
			function updateStatutsTrigger(e) {
				overlay = Overlay('uploader')
				tag = e.findParent('.icon.edit').previousElementSibling.querySelector('a')
				url = tag.getAttribute('href')
				name = tag.download

				overlay.child('.title').innerText = "Modification d'une entrée"
				overlay.child('.selected-file').innerText = name;
				overlay.child('.frame').show()
				overlay.child('.fileentry').required = false;
				overlay.toggle()

				overlay.input('filename').dataset.name = url.filename(true)
				overlay.input('filename').value = url.filename()
				overlay.input('fileextension').value = url.extension()
				overlay.input('filename').readOnly = true
				overlay.input('desc').value = tag.dataset.alias
			}

			// update statuts metadata on server
			async function updateStatuts(e) {
				null
			}

			// update file description on server
			async function descUpdate(form) {
						url = currentFile.url
						tag = currentFile.tag
						currentFile = {};

						fileTag = Overlay('uploader').child('.fileentry')

						formData = new FormData(form);
						formData.set('mode', mode)
						formData.set('source', 'uploads/pdf/')
						formData.set('action', 'update')
						formData.set('subcategory', subcategory)	
						formData.set('og-url', url)
						formData.set('url', tag.dataset.targetUrl)
						formData.set('filename', formData.get('filename') + '.' + Overlay('uploader').input('fileextension').value)
						formData.set('file-updated', mode === 'update' && url !== tag.dataset.targetUrl)
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
							
							notify(`Error [${errorCode}]: ${errorData}`, 'red');							
							return
						} else {// correct sql response
							notify('Data updated successfully')
						}

						if (mode === 'add') {
							li = document.createElement('li');
							li.innerHTML = `
									<div class='icon delete inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'delete', 'bf')" title="Supprimer cette entrée"><span class='img'></span></div>
									<div class='icon edit inline medium dynamic hoverable' onclick="changeEntryTrigger(event.target, 'edit', 'bf')" title="Modifier cette entrée"><span class='img'></span></div>
									<a href="${data.data.path}" data-name="${data.data.name}" target="_blank_" download>${data.data.desc}</a> <small><i>(${formatBytes(data.data.size, 1)})</i></small>
								`
							document.querySelector(`.${subcategory}`).prepend(li)
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
						subcat = currentFile.subcat;
						currentFile = {};

						// extract stored data to update it accordingly
						jsonData = JSON.stringify({'csrf':csrfToken, 'action':'reference', 'mode':'fetch', 'url':url})
						data = await fetchAPI('/admin/engine/files', jsonData).then(response => response.body)

						// update used_by
						usage = data.data['used_by'];
						newUsage = usageUpdate('documents/'+subcat, usage)

						// update data on server
						jsonData = JSON.stringify({'csrf':csrfToken, 'action':'reference', 'mode':'delete', 'url':url, 'usage':newUsage})
						response = await fetchAPI('/admin/engine/files', jsonData)

						if (response.ok) {
							notify('Entry deleted successfully.', 'green');

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
			Button('new-bm').onclick = e => {
				overlay = e.container()
				overlay.child('form').reset();
				overlay.child('.fileentry').required = true;
				overlay.child('.frame').hide();
				overlay.child('.title').innerText = "Ajout d'une entrée"
				subcategory = 'bm';
				mode = 'add';
				overlay.toggle()
			}

			// show panel to save changes
			Button('new-bf').onclick = e => {
				overlayAdd.child('form').reset();
				document.querySelector('#overlay-add .fileentry').required = true;
				document.querySelector('#overlay-add .title').innerText = "Ajout d'une entrée"
				subcategory = 'bf';
				mode = 'add';
				overlayAdd.toggle()
			}

			Overlay('uploader').button('cancel').onclick = e => {
				overlay = e.container()
				overlay.hide();
				overlay.child('form').reset();
				overlay.child('.selected-file').innerText = '';
				overlay.input('filename').readOnly = false
				currentFile = {};
			}

			Overlay('validate').button('cancel').onclick = e => {
				e.container().hide();
				currentFile = {};
			}

			Overlay('validate').button('validate').onclick = e => {
				e.container().hide();
				removeElement()
			}

			Overlay('uploader').child('form').onsubmit = e => {
				e.preventDefault();
				e.container().hide();
				
				descUpdate(e.target)
			}

			Overlay('uploader').icon('delete').onclick = e => {
				overlay = e.container()
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
					e.container().toggle();
					Overlay('filepicker').toggle();
			}

			Overlay('filepicker').button('validate').onclick = e => {
					tag = overlayChoose('.file-element[selected]')
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
						
						Overlay('uploader').input('filename').dataset.name = url.filename(true)
						Overlay('uploader').input('filename').value = url.filename()
						Overlay('uploader').input('fileextension').value = url.extension()
						Overlay('uploader').input('filename').readOnly = true

						e.container().childs('.file-element').forEach(tag => {tag.unset('selected')})
						Overlay('uploader').child('.selected-file').innerText = url.filename(true);
						Overlay('uploader').child('.frame').show();
						Overlay('uploader').child('.fileentry').required = false;
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

		async function fileSelectorLoad(category=null) {
				container = overlayChoose.child('.files-list-container');
				container.innerHTML = '<div class="spinner"></div>';

				jsonData = JSON.stringify({'csrf':csrfToken, 'action':'fetch', 'category':category})
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
		<?php endif; ?>
		</script>
    </body>
</html>

