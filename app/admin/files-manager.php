<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
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
		<title>Gestion des fichiers</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Gestion des fichiers">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		<script src='/files/assets/js/grid.js'></script>
		<script src='/files/assets/js/jsonview.js'></script>
		<script src='/files/assets/js/upload.js'></script>

		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>

		<style>
			@media(hover:hover) {.grid-item:hover .icon.dots {filter: invert(1);}}
			@media(hover:none) {.grid-item-preview[active] ~ .icon.dots {filter: invert(1);}}

			.icon.dots {z-index: 2}

			#files-list:not([switched]) {
				& #grid-wrap {display: none;}
				}
			
			#files-list[switched] {
				& #files {display: none;}
				}
			
			.grid-item-wrap {
					margin-top: 0 !important;

					& .grid-item {
							&[invalid] {border-color: red !important; border-width: 3px !important;}
							}
					}
			
			.scan-info-wrap {
					display: flex;
					flex-direction: column;
					gap: .75em;
					margin-bottom: 2em;

					& .files-wrap {
							border: 2px solid black;
							border-radius: 1em;
							padding: .75em;
							display: none;
							position: relative;
							
							&[active] {display: block;}
							&.unregistered {border-color: #500c7e;background-color: #500c7e15;}
							&.invalid {border-color: red; background-color: #f92f2f15;}
							&.recovered {border-color: green; background-color: #43f92f15;}
							&.changed {border-color: orange; background-color: #f9972f15;}

							& .json-container {
									background-color:  unset !important;
									overflow: auto;
									
									& .json-value {white-space: nowrap;}
									}
							
							& .desc {margin-bottom: .75em;}
							& .container {margin-bottom: .75em;}
							}
					}
			
			.icons-bar {
					position: absolute;
					bottom: 6px;
					left: 30%;
					display: none;
					z-index: 5;

					&[active] {display: flex;}
					}
			
					.grid-item:not(:hover) .icons-bar {display: none}

			.use-warning {
					display: none;
					margin-top: .5em;
					&[active] {display: block;}

					& ul {margin: .25em 0}
					}
			
			.delete-mode-wrap {
					margin: 1em 0;
					display: none;

					&[active] {display: block;}

					& label {cursor: pointer;}
					}

			.checkbox-wrap {max-width: 16em; cursor: pointer;}

			#filename, #fileextension {font-size: .7em}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img' ></div></a>
					<h1>Fichers stockés sur le serveur</h1>
			</div>
			
			<div class="box linear">
				<div class='icon layout inline large hoverable dynamic'><span class='img'></span></div>
				<div class='icon refresh inline large hoverable dynamic' title="Exécuter un scan"><span class='img'></span></div>
				<div class='icon upload inline large hoverable dynamic' title="Ajouter un fichier sur le serveur"><span class='img'></span></div>
			</div>			

			<div class="scan-info-wrap">
				<div class="files-wrap unregistered">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced><span class='img'></span></div>
					<div class="desc"></div>
					<div class="container"></div>

					<div class="form-action-wrap linear">
						<div class='form-action'>
							<button class='action-btn add-files'>Les ajouter</button>
						</div>
						<div class='form-action'>
							<button class='action-btn del-files'>Les supprimer</button>
						</div>
					</div>
				</div>
				
				<div class="files-wrap invalid">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced><span class='img'></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap changed">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced><span class='img'></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap recovered">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced><span class='img'></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>
			</div>
			
			<div id="files-list">
				<div class='table no-bound' id='files' active></div>

				<div id='grid-wrap'>
					<div id="grid-container">
					</div>
					
					<div id="news-footer">
						<div class="news-nav-wrap">
							<div class="spinner"></div>
							<div class="news-count"></div>
						</div>
					</div>
				</div>

				<div class="form-action-wrap data-actions linear responsive">
					<div class='form-action'>
						<button class='action-btn save'>Sauvegarder</button>
					</div>
					<div class='form-action'>
						<button class='action-btn reset'>Réinitialiser</button>
					</div>
				</div>

			</div>
			<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
		</div>
		

		<div class="body-overlay" id="overlay-preview">
			<div class="inner-overlay">
				<div class="file-preview" active><img></div>

				<div class='external form-action-wrap linear'>
					<div class='form-action'>
						<button class='action-btn close'>Fermer</button>
					</div>
				</div>
			</div>
		</div>
		
		<div class="body-overlay" id="overlay-deletion">
			<div class="inner-overlay">
				<div class="header">
					<h4 class="title">
						<span></span>
					</h4>
				</div>
				
				<div class="use-warning">
					<div>
						<span>Ce fichier est utilisé par :</span>
						<ul></ul>
						<span>Sa suppression le rendra inaccessible pour ces ressources</span>
					</div>
				</div>
				
				<div>
					<div><strong>Attention !</strong></div>
					<div>La suppression est <strong>définitive</strong>.</div>
				</div>
				<div class="delete-mode-wrap">
					<input type="checkbox" id="deletion-mode" value="deletion" name="delete"><label for="deletion-mode">Supprimer l'entrée du fichier de la base de données</label>
				</div>

				<div class='external form-action-wrap linear'>
					<div class='form-action'>
						<button class='action-btn validate'>Continuer</button>
					</div>
					<div class='form-action'>
						<button class='action-btn cancel'>Annuler</button>
					</div>
				</div>
			</div>
		</div>

		<?php include($root . '/app/templates/overlay_file-picker.html') ?>
		<?php include($root . '/app/templates/overlay_file-uploader.html') ?>
		<?php include($root . '/app/templates/overlay_authentification.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
			let filesList;				// array containing data about registered files
			let pendingFiles;			// array containing data about unregistered files
			
			
		/// GENERAL ACTION
			// On-load logic
			window.addEventListener('load', loader);
			async function loader()  {
										// configure overlay
										overlay = Overlay('uploader')
										overlay.toggle('strict')			
										overlay.querySelector('.exist-file').parentElement.style.display = 'none';			
										overlay.querySelector('.file-maxsize').firstElementChild.innerText = 'Taille maximale: 10 Mo';			
										overlay.querySelector('.file-formats').firstElementChild.innerText = 'Formats supportés: image, .pdf, .sqlite, .zip';			
										overlay.input('file').setAttribute('accept', 'application/zip,application/pdf,application/vnd.sqlite3,image/*')			
										
										div = document.createElement('div')
										div.classList.toggle('checkbox-wrap')
										div.innerHTML = `<div class='input-container'>
															<input class='checkbox' type='checkbox' name='admin-check' id='admin-check' autocomplete=off title="Restreindre l'accès aux administrateurs"><label class='label' for='admin-check'>Restreindre l'accès aux administrateurs</label>
														</div>`
										overlay.child('form').insertBefore(div, overlay.querySelector('.external.form-action-wrap'))

										// fetch all required data
										await fetchFiles('#files');
										total = filesList.length;
										fileScanner()
										
										// easy match table with their type
										tableMatch = {
													'files': {'table': filesTable, 'source': JSON.stringify(filesTable.getData()), 'call': fetchFiles, 'selector': '#files', 'type': 'files'},
													}
										activeTable = tableMatch['files']
										
										tablesSwitcher()
										
										// fetch most recent previews
										fetchControl()
										}

			// Auth validation before redirection
			Overlay('auth').child('form').onsubmit = e => e.preventDefault();
			Overlay('auth').button('validate').onclick = async e => {
					validity = await passwordChecker();
					if (validity) {
						e.container().hide();	
						pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
					}
			}
		
			// confirm deletion
			Overlay('deletion').button('validate').onclick = e => {
					e.container().toggle();
					Overlay('auth').toggle();
					pendingAction['mode'] = Overlay('deletion').input('deletion-mode').checked
			}
			
			// close scan message
			function closer(e) {
				e.target.findParent('.files-wrap').hide()
			}

			async function updateData() {
					data = await dataFetcher('files');
					filesList = data['sql-data'];
					filesTable.setData(filesList).then(statusWizard)
					gridContainer.innerHTML = '';
					
					await buildFiles(3*nGridItems)
					fetchControl();

					return data;
			}
			
		/// FILES LAYOUT
			// Add articles data to DOM
			async function buildFiles(append) {
							start = received;
							received += append;
							received = received > total ? total:received;
							
							// add articles
							for (i=start; i < received; i++) {
									file = filesList[i];

									// file data
									url  = file.url;
									name = file.name;
									time = file['updated_at']
									type = file.type;
									size = file.size;
									status = file.status;

									if (!file.type.match(/image\/.*/)) {
										imgURL = Object.keys(mimeTable).includes(file.type) ? '/files/icons/' + mimeTable[file.type] : '/files/icons/unknown.svg';
									} else {
										imgURL = status === 'true' ? url.replace(/admin:\/\//, '/admin') : '/files/icons/picture.svg';
									}

									bgData = file.type.match(/image\/.*/) && imgURL !== '/files/icons/picture.svg' ? `
			<div class='article-poster poster-bg'>
				<img loading="eager" src="${imgURL}">
			</div>
			` : '';
									// HTML code for one file
									div = document.createElement('div');
									div.classList.toggle('grid-item-wrap')
									html = `
		<div class='grid-item' ${status === 'true' ? '':'invalid'}>
			${bgData}

			<div class='article-poster poster-fg'>
				<img loading="eager" src="${imgURL}" data-url="${imgURL}" data-type="${name.extension()}">
			</div>

			<div class="grid-item-preview full">
				<h4>${name}</h4>
				
				<span>Modifié le : ${time}</span>
				<span>Taille : ${formatBytes(size)}</span>
				<span>Téleversé par ${file.uploader}</span>
			</div>

			<div class='icon dots right medium dynamic base-shdw' title="Afficher les métadonnées"><span class='img'></span></div>
			<div class='icons-bar inverted' data-url='${url}'>
				<div class='icon visualize medium inline hoverable dynamic invertable' title="Voir le fichier" ><span class='img'></span></div>
				<div class='icon download medium inline hoverable dynamic invertable' title="Télécharger le fichier"><span class='img'></span></div>
				
			</div>
		</div>	
									`
									
									// Add to DOM
									div.innerHTML = html
									gridContainer.append(div);

									// add event on click
									if (window.matchMedia(`(hover: none)`).matches) {
										div.querySelector('.grid-item').addEventListener('click', e => e.target.findParent('.grid-item').querySelector('.grid-item-preview').toggle())
									}
									
									div.icon('visualize').addEventListener('click', e => {e.stopPropagation(); viewFile(e.target.findParent('.icons-bar').dataset.url)})
									div.icon('download').addEventListener('click', e => {e.stopPropagation(); downloadFile({'url': e.target.findParent('.icons-bar').dataset.url})})
									div.icon('dots').addEventListener('click', e => {e.stopPropagation(); e.target.findParent('.dots').nextElementSibling.toggle()})
							}
							
							// add general data
							plural = received > 1 ? 's':'';
							total > 0 ? document.querySelector('.news-count').innerHTML = `${received} fichier${plural} affiché${plural} sur ${total}`:null;
							
							return null
						}
			
			// trigger DOM append
			window.onscroll = fetchControl
			async function fetchControl() {
					// end of news in viewport
					condition = !lock && received < total && document.querySelector('.news-nav-wrap').getBoundingClientRect().y < 0.95 * window.innerHeight;
					
					if (condition) {
						lock = true
						Spinner().show()
					
						// fetch new results
						await sleep(750)
						await buildFiles(3*nGridItems)

						Spinner().hide()
						lock = false
					}
			}

			// display layout management
			Icon('layout').onclick = e => {
												e.target.parentNode.toggle();
												document.querySelector('#files-list').toggle('switched');
												document.querySelector('#files-list').getAttribute('switched') !== null ? fetchControl():null;
												};
			
			function statusWizard() {
				Array.from(filesTable.getColumn('status').getCells()).forEach(cell => {cell.getValue() ? null:cell.getElement().findParent('.tabulator-row').toggle('invalid')})
			}
	
			function viewFile(url, activeOverlay=null) {
				url = url.replace(/admin:\/\//, '/admin')
				ext = url.extension();
				
				if (ext === 'pdf') {
					open(url)
				} else {
					overlay = Overlay('preview');
					overlay.toggle();
					overlay.querySelector('.file-preview img').src = url

					activeOverlay ? document.querySelector(`$#overlay-${activeOverlay}`).toggle():null;
					pendingAction = {'overlay': activeOverlay}
				}
			}
		/// SCAN
			Icon('refresh').onclick = fileScanner;
			async function fileScanner(e = null) {
						notify('Scan initiated', 'orange')
						e ? e.target.findParent('.icon').show():null;
						
						document.querySelector('.files-wrap.invalid').hide();
						document.querySelector('.files-wrap.unregistered').hide()
						document.querySelector('.files-wrap.recovered').hide();

						// update data on server
						jsonData = JSON.stringify({'csrf':csrfToken, 'action':'scan'})
						files = await fetchAPI('/admin/engine/files', jsonData).then(response => response.body)

						descMatch = {
							'unregistered': null
						}

						if (files.unregistered.length !== 0) {
							data = files.unregistered;

							wrap = document.querySelector('.files-wrap.unregistered');
							wrap.show();

							nb = data.length;
							plural = nb > 1 ? 's':'';

							// update DOM content
							wrap.querySelector('.desc').innerHTML = `<strong>${nb} fichier${plural}</strong> sur le serveur ${nb > 1 ? 'ont':'a'} été détecté${plural} sans être enregistré${plural} dans la base de données :`;
							wrap.querySelector('.container').innerHTML = '';
							jsonview.renderJSON(JSON.stringify(data), wrap.querySelector('.container'))
							
							// export data globallly
							pendingFiles = data;
						}

						if (files.invalid.length !== 0) {
							data = files.invalid;

							wrap = document.querySelector('.files-wrap.invalid');
							wrap.show();

							nb = data.length;
							plural = nb > 1 ? 's':'';

							// update DOM content
							wrap.querySelector('.desc').innerHTML = `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données n'existe${nb > 1 ? 'nt':''} plus sur le serveur :<br><small><i>${nb > 1 ? 'Leur':'Son'} status a été mis à jour.</i></small>`;
							wrap.querySelector('.container').innerHTML = '';
							jsonview.renderJSON(JSON.stringify(data), wrap.querySelector('.container'))

							await updateData()
						}

						if (files.recovered.length !== 0) {
							data = files.recovered;

							wrap = document.querySelector('.files-wrap.recovered');
							wrap.show();

							nb = data.length;
							plural = nb > 1 ? 's':'';

							// update DOM content
							wrap.querySelector('.desc').innerHTML = `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données mais réputé${plural} inaccessible${plural} ${nb > 1 ? 'ont':'a'} été détecté${plural} sur le serveur :<br><small><i>${nb > 1 ? 'Leur':'Son'} status a été mis à jour.</i></small>`;
							wrap.querySelector('.container').innerHTML = '';
							jsonview.renderJSON(JSON.stringify(data), wrap.querySelector('.container'))

							await updateData()
						}

						if (files.changed.length !== 0) {
							data = files.changed;

							wrap = document.querySelector('.files-wrap.changed');
							wrap.show();

							nb = data.length;
							plural = nb > 1 ? 's':'';

							// update DOM content
							wrap.querySelector('.desc').innerHTML = `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données différent${plural} ${plural} ${nb > 1 ? 'des':'du'} fichier${plural} détecté${plural} sur le serveur.`;
							wrap.querySelector('.container').innerHTML = '';
							jsonview.renderJSON(JSON.stringify(data), wrap.querySelector('.container'))

							await updateData()
						}

						e ? e.target.findParent('.icon').hide():null;
						notify('Scan complete', 'green')
			}


		/// FILES ACTIONS
			// Download file
			async function downloadFile(rowdata) {
					url = rowdata.url
					
					if (url.match(/^admin:\/\//)) {
						jsonData = JSON.stringify({'csrf':csrfToken, 'action':'encode', 'url':url})
						data = await fetchAPI('/admin/engine/files', jsonData).then(response => response.body)

						bytes = Uint8Array.fromBase64(data.base64);
						blob = new Blob([bytes], {type: rowdata.type});
					} else {
						blob = await fetchBlob(url);
					}
					
					download(blob, url.filename(true))
			}

			// Manage unregistered files
			async function filesInserter(elements) {
					notify((pendingAction.action === 'insert' ? 'Insertion':'Suppression')+' en cours', 'orange')
					
					selectedFiles = Array.from(elements).map(elm => {elm.unset('selected'); return elm.dataset.url})
					action = pendingAction.action;
					document.querySelector('.files-wrap.unregistered').hide();

					wrapper = Overlay('filepicker').querySelector('.files-list-container')
					wrapper.innerHTML = '';
					pendingAction = {}

					// update data on server
					jsonData = JSON.stringify({'csrf':csrfToken, 'action':action, 'files':selectedFiles})
					response = await fetchAPI('/admin/engine/files', jsonData)

					if (!response.ok) {
						notify(response.body.error, 'red')
					} else {
						notify(response.body.content, 'green')
					}
			}
			
			async function deleter(data) {
					notify('Operation started', 'orange')
					mode = pendingAction.mode ? 'force':'keep';
					pendingAction = {};
					Overlay('deletion').input('deletion-mode').checked = false;

					// update data on server
					jsonData = JSON.stringify({'csrf':csrfToken, 'action':'remove', 'mode':mode, 'url':data.url, 'status':data.status})
					response = await fetchAPI('/admin/engine/files', jsonData)

					if (!response.ok) {
						notify(response.body.error, 'red')
					} else {
						await updateData()
						notify('Operation succeeded', 'green')
					}
			}

			// show file metadata on unhover display
			document.querySelectorAll('.icon.dots').forEach(item => {
						item.onclick = e => {
							e.target.findParent('.icon').nextElementSibling.toggle()
						}
					})

			// Action for unregistered files
			Button('add-files').onclick = e => {
					pendingAction = {'action': 'insert'}
					selectionGenerator()
			}
			Button('del-files').onclick = e => {
					pendingAction = {'action': 'delete'}
					selectionGenerator()
			}

			function selectionGenerator() {
					wrapper = Overlay('filepicker').querySelector('.files-list-container')
					files = pendingFiles

					files.forEach(file => {
						div = document.createElement('div');
						div.classList.toggle('file-element-wrap')

						div.innerHTML = `
							<div class='file-element-icon'>
								<div class='icon visualize medium inline hoverable dynamic' title="${file.path}"><span class='img'></span></div>
								<div class='icon download medium inline hoverable dynamic' title="${file.path}"><span class='img'></span></div>
							</div>
							<div class='file-element' data-url="${file.path}">${file.name}</div>
						`
						div.querySelector('.file-element').addEventListener('click', e => e.target.findParent('.file-element').toggleAttribute('selected'))
						div.icon('visualize').addEventListener('click', e => viewFile(e.target.findParent('.file-element-wrap').querySelector('.file-element').dataset.url))
						div.icon('download').addEventListener('click', e => downloadFile({'url': e.target.findParent('.file-element-wrap').querySelector('.file-element').dataset.url}))

						wrapper.append(div)
					})

					Overlay('filepicker').toggle()
			}

			Overlay('filepicker').button('validate').onclick = e => {
					tags = e.container().querySelectorAll('.file-element[selected]')
					error = e.container().error('file-choice')

					if (tags.length === 0) {
						error.innerText = 'Sélectionnez au moins un fichier pour continuer';
						error.show();
					} else {
						error.hide();
						e.container().hide();

						filesInserter(tags)
					}
			}
		
		//// FILE UPLOAD
			Icon('upload').onclick = e => Overlay('uploader').toggle()
			
			Overlay('uploader').button('cancel').onclick = e => {
				overlay = e.container()
				overlay.hide();
				overlay.child('form').reset();
				overlay.child('.selected-file').innerText = '';
				overlay.child('.frame').hide();
				overlay.input('file').required = true;
			}

			Overlay('uploader').icon('delete').onclick = e => {
				Overlay('uploader').child('.frame').hide();
				Overlay('uploader').child('.selected-file').innerText = '';
				Overlay('uploader').input('file').value = '';
			}

			document.querySelector('#file').onchange = e => handlePassedFile(e.target)

			Overlay('uploader').child('form').onsubmit = e => {
				e.preventDefault();
				e.container().hide();
				
				uploader(e.target)
			}

			function fileDropHandle(e) {
						allowDrop(e);
						
						data = e.dataTransfer;
						files = data.files;
						
						input = document.querySelector('#file');
						input.files = files;
						
						handlePassedFile(input)
				}

			function handlePassedFile(input) {
						upload = fileUploadManage(input, /(application\/(pdf|zip|x-zip-compressed|vnd\.sqlite3)|image\/.*)/, 10)
							if (upload) {
								Overlay('uploader').input('filename').value = file.name.filename();
								Overlay('uploader').input('filename').dataset.name = file.name;
								Overlay('uploader').input('fileextension').value = file.name.extension();
								}
			}

			// update new file
			async function uploader(form) {
						notify('Uploading file', 'orange')
						fileTag = Overlay('uploader').child('.fileentry')
						adminCheck = Overlay('uploader').input('admin-check').checked
						file = fileTag.files[0];
						
						// category congif
						if (file.type.match(/image\/.*/)) {
							category = 'poster'
							subcategory = null
						} else if (file.type.match(/application\/zip/)) {
							category = null
							subcategory = 'archive'
						} else if (file.type.match(/application\/vnd\.sqlite3/)) {
							category = null
							subcategory = 'database'
						}else if (file.type.match(/application\/pdf/)) {
							category = 'document'
							subcategory = null
						}

						// url config
						if (adminCheck) {
							url = 'admin:///files/'+file.name
						} else {
							url = '/files/uploads/'+file.name
						}
						
						formData = new FormData(form);
						formData.set('mode', 'add')
						formData.set('action', 'update')
						formData.set('subcategory', subcategory)	
						formData.set('category', category)	
						formData.set('og-url', url)
						formData.set('url', url)
						formData.set('filename', formData.get('filename')+'.'+Input('fileextension').value)
						formData.set('admin', adminCheck)
						if (!adminCheck) formData.set('source', 'uploads/')
						formData.set('file-updated', false)
						formData.set('csrf', csrfToken)

						// Response object
						response = await fetchAPI('/admin/engine/files', formData)

						if (!response.ok) {// http code 400-500 reuturn false
								alert(`Error: HTTP ${response.httpcode}: ${response.body.error}`);
								return null
								}
						
						if (response.httpcode === 250) {// failed sql request
							errorData = response.body.error;
							errorCode = response.body.code;
							
							notify(`Error [${errorCode}]: ${errorData}`, 'red');							
							return
						} else {// correct sql response
							notify('File uploaded', 'green')
						}

						form.reset()
						Overlay('uploader').child('.selected-file').innerText = ''
						Overlay('uploader').child('.frame').hide()
						await updateData()
					}
		
			// close file viewer
			Overlay('preview').button('close').onclick = e => {
					e.container().toggle();
					e.container().child('.file-preview img').src = '';

					activeOverlay = pendingAction.overlay;
					activeOverlay ? document.querySelector(`$#overlay-${activeOverlay}`).toggle():null;
					pendingAction = {}
			}

			// close unregistered files selector
			Overlay('filepicker').button('cancel').onclick = e => {
					wrapper = e.container().querySelector('.files-list-container')
					document.error('file-choice').hide();
					e.container().toggle();
					wrapper.innerHTML = '';
					pendingAction = {}
			}

			// close deletion control
			Overlay('deletion').button('cancel').onclick = e => {
					e.container().input('deletion-mode').checked = false;
					e.container().hide()
					pendingAction = {}
			}

			// close deletion control
			Overlay('auth').button('cancel').onclick = e => {
					Overlay('deletion').input('deletion-mode').checked = false;
					e.container().hide()
					pendingAction = {}
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
		
		//// DATA EDITION
			Button('reset').onclick = e => {
					pendingAction.function = resetTabledata;
					Overlay('validate').show();
			}

			// show overlay save changes
			Button('save').onclick = e => {
					pendingAction.function = saveCurrentData;
					Overlay('auth').show();
			}

			async function saveCurrentData() {
				// active table
				table = activeTable.table;

				// select edited rows, with values different from original
				modifiedRows = Array.from(table.getEditedCells().concat(Object.values(autoEditedCells))).map(c => {return c.getValue() !== c.getInitialValue() ? c.getRow().getData():null}).filter((item, index, arr) => (item !== null && arr.indexOf(item) === index));

				// update fetch
				jsonData = JSON.stringify({'action':'placeholder', 'type':activeTable.type, 'csrf':csrfToken, 'data':modifiedRows})
				response = await fetchAPI('/admin/engine/files', jsonData)

				if (!response.ok) {
					notify('Échec de la mise à jour', 'red')
					return
				} else {
					document.querySelector('#main').child('.form-action-wrap').hide()
					
					activeTable.source = JSON.stringify(activeTable.table.getData())
					resetState(activeTable)

					if (activeTable.type === 'tags') {
						null
						// UPDATE TAGS MATCH TABLE
					}

					notify('Données mises à jour', 'green')
				}
				}
		</script>
	</body>
</html>