<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

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
							&.invalid	{border-color: red; background-color: #f92f2f15;}
							&.moved 	{border-color: maroon; background-color: #52352415;}
							&.changed	{border-color: orange; background-color: #f9972f15;}
							&.replaced	{border-color: violet; background-color: #8c378f15;}
							&.recovered {border-color: green; background-color: #43f92f15;}
							&.unregistered {border-color: #500c7e;background-color: #500c7e15;}
							&.duplicate {border-color: darkcyan; background-color: #53769415;}

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
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>
					<h1>Fichers stockés sur le serveur</h1>
			</div>
			
			<div class="box linear">
				<div class='icon layout inline large hoverable dynamic'></div>
				<div class='icon refresh inline large hoverable dynamic' title="Exécuter un scan"></div>
				<div class='icon upload inline large hoverable dynamic' title="Ajouter un fichier sur le serveur"></div>
			</div>			

			<div class="scan-info-wrap">
				<div class="files-wrap invalid">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>
				
				<div class="files-wrap moved">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap changed">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap replaced">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap recovered">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></span></div>
					<div class="desc"></div>
					<div class="container"></div>
				</div>

				<div class="files-wrap unregistered">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></div>
					<div class="desc"></div>
					<div class="container"></div>

					<div class="actions-wrap linear">
						<div class='action-container'>
							<button class='action-btn add-files'>Les ajouter</button>
						</div>
						<div class='action-container'>
							<button class='action-btn del-files'>Les supprimer</button>
						</div>
					</div>
				</div>

				<div class="files-wrap duplicate">
					<div class='icon float cross small hoverable' onclick="closer(event)" forced></div>
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

				<div class="actions-wrap data-actions linear">
					<div class='action-container'>
						<button class='action-btn responsive save'>Sauvegarder</button>
					</div>
					<div class='action-container'>
						<button class='action-btn responsive reset'>Réinitialiser</button>
					</div>
				</div>

			</div>
			<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
		</div>
		

		<div class="body-overlay" id="overlay-preview">
			<div class="inner-overlay">
				<div class="file-preview" active><img><iframe class="activable"></iframe></div>

				<div class='external actions-wrap linear'>
					<div class='action-container'>
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

				<div class='external actions-wrap linear'>
					<div class='action-container'>
						<button class='action-btn validate'>Continuer</button>
					</div>
					<div class='action-container'>
						<button class='action-btn cancel'>Annuler</button>
					</div>
				</div>
			</div>
		</div>

		<?php include($root . '/app/templates/overlay_file-picker.html') ?>
		<?php $mode="global"; include($root . '/app/templates/overlay_file-uploader.php') ?>
		<?php include($root . '/app/templates/overlay_authentification.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
			let filesList				// array containing data about registered files
			let pendingFiles			// array containing data about unregistered files
			
			
		/// GENERAL ACTION
			// On-load logic
			window.addEventListener('load', loader);
			async function loader()  {
										// fetch all required data
										filesList = await fetchFiles('#files')
										total = filesList.length
										fileScanner()
										
										// easy match table with their type
										tableMatch = {
													'files': {'table': filesTable, 'node': Table('files'), 'source': JSON.stringify(filesTable.getData()), 'call': fetchFiles, 'selector': '#files', 'type': 'files'},
													}
										activeTable = tableMatch['files']
										
										tablesSwitcher()
										fetchControl()

										Overlay('filepicker').section('file-preview').toggle()
										}

			
			
			// close scan message
			function closer(e) {
				e.target.parent('.files-wrap').hide()
			}

			async function updateData(force=false) {
					filesList = await dataFetcher('files', null, 'files')
					filesTable.setData(filesList).then(statusWizard)
					gridContainer.innerHTML = '';
					
					await buildFilesFlow(3*nGridItems, force)
					fetchControl()

					return filesList
			}
			
		/// FILES LAYOUT
			// trigger DOM append
			window.onscroll = () => fetchControl()

			// display layout management
			Icon('layout').onclick = e => {
											e.target.parentNode.toggle()
											Block('files-list').toggle('switched')
											if (Block('files-list').getAttribute('switched') !== null) fetchControl()
											}
			
			function statusWizard() {
				Array.from(filesTable.getColumn('status').getCells()).forEach(cell => cell.getValue() ? null:cell.getElement().parent('.tabulator-row').toggle('invalid'))
			}
		
		/// SCAN
			Icon('refresh').onclick = fileScanner
			async function fileScanner(e = null) {
						notify("Scan en cours", "Analyse en cours des fichiers stockés sur le serveur et enregistrés dans la base de données.", 'orange')
						e?.target.show()
						
						const types = ['invalid', 'moved', 'changed', 'replaced', 'recovered', 'unregistered', 'duplicate']
						types.forEach(type => Section(`files-wrap.${type}`).hide())

						// update data on server
						const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'scan'})
						const files = await fetchAPI('/admin/engine/files', jsonData).then(response => response.body)
						
						types.forEach(async type => {
							const data = files[type]
							
							if (data.length > 0) {
								const nb = data.length
								const plural = nb > 1 ? 's':'';
								const wrap = Section(`files-wrap.${type}`)
								const container = wrap.section('container')
								
								const descMatch = {
									'invalid'		: `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données n'existe${nb > 1 ? 'nt':''} plus sur le serveur :<br><small><i>${nb > 1 ? 'Leur':'Son'} status a été mis à jour.</i></small>`,
									'moved'			: `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données ${nb > 1 ? 'ont':'a'} été détecté${plural} à un autre emplacement sur le serveur.`,
									'changed'		: `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données ${nb > 1 ? 'ont':'a'} un contenu différent ${nb > 1 ? 'des':'du'} fichier${plural} détecté${plural} sur le serveur.`,
									'replaced'		: `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données mais manquant${plural} ${nb > 1 ? 'ont':'a'} été détecté${plural} sur le serveur avec un contenu différent :`,
									'recovered'		: `<strong>${nb} fichier${plural}</strong> enregistré${plural} dans la base de données mais réputé${plural} inaccessible${plural} ${nb > 1 ? 'ont':'a'} été détecté${plural} sur le serveur :<br><small><i>${nb > 1 ? 'Leur':'Son'} status a été mis à jour.</i></small>`,
									'unregistered'	: `<strong>${nb} fichier${plural}</strong> sur le serveur ${nb > 1 ? 'ont':'a'} été détecté${plural} sans être enregistré${plural} dans la base de données :`,
									'duplicate'		: `<strong>${nb} doublon${plural}</strong> de fichiers enregistrés dans la base de données détecté${plural}.`
								}
								
								container.innerHTML = '';
								jsonview.renderJSON(JSON.stringify(data), container)
								wrap.section('desc').innerHTML = descMatch[type]
								wrap.show()

								if (type === 'unregistered') pendingFiles = data
								await updateData(true)
							}
						})

						e?.target.hide()
						notify("Scan terminé", "L'analyse des fichiers est terminée.", 'green')
			}


		/// FILES ACTIONS
			// Download file
			async function downloadFile(rowdata) {
					const url = rowdata.url
					let blob
					
					if (url.match(/^admin:\/\//)) {
						const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'encode', 'url':url})
						const data = await fetchAPI('/admin/engine/files', jsonData).then(response => response.body)

						const bytes = Uint8Array.fromBase64(data.base64)
						blob = new Blob([bytes], {type: rowdata.type})
					} else {
						blob = await fetchBlob(url)
					}
					
					download(blob, url.filename(true))
			}

			// Manage unregistered files
			async function filesInserter(elements) {
					notify(`${pendingAction.action === 'insert' ? 'Ajout':'Suppression'} en cours`, `Les fichiers détectés sur le serveur n'existant pas sur la base de données sont en cours ${pendingAction.action === 'insert' ? "d'enregistrement":'de suppression'}.`, 'orange')
					
					const selectedFiles = Array.from(elements).map(elm => {elm.unset('selected'); return elm.dataset.url})
					const action = pendingAction.action
					const wrapper = Overlay('filepicker').section('files-list-container')
					
					Section('files-wrap.unregistered').hide()
					wrapper.innerHTML = '';
					pendingAction = {}

					// update data on server
					const jsonData = JSON.stringify({'csrf':csrfToken, 'action':action, 'files':selectedFiles})
					const response = await fetchAPI('/admin/engine/files', jsonData)
					
					if (!response.ok) {
						notify("Erreur lors de l'opération", response.body.error, 'red')
					} else {
						notify("Succès de l'opération", response.body.content, 'green')
					}
			}
			
			async function deleter(data) {
					const mode = pendingAction.mode ? 'force':'keep';
					pendingAction = {}
					Overlay('deletion').input('deletion-mode').checked = false

					notify("Suppression en cours", `La suppression du fichier '${data.url}' du serveur est en cours.${mode === 'force' ? " L'entrée correspondante dans la base de données sera supprimée.":''}`, 'orange')

					// update data on server
					const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'remove', 'mode':mode, 'url':data.url, 'status':data.status})
					const response = await fetchAPI('/admin/engine/files', jsonData)

					if (!response.ok) {
						notify("Erreur lors de la suppression", `Le fichier '${data.url}' n'a pas pu être retiré du serveur. Veuillez réessayer.`, 'red')
					} else {
						await updateData()
						notify("Suppression réussie", `Le fichier '${data.url}' a été retiré du serveur${mode === 'force' ? " et de la base de données.":'.'}`, 'green')
					}
			}

			function selectionGenerator() {
					const wrapper = Overlay('filepicker').section('files-list-container')
					const files = pendingFiles

					files.forEach(file => {
						const div = document.createElement('div')
						const [group, extension] = file.type.split('/')
						const view = (group === 'application' && ['pdf', 'sql'].includes(extension)) || group === 'text' || group === 'image'
						
						div.classList.toggle('file-element-wrap')
						div.innerHTML = `
							<div class='file-element-icon'>
								<div class='icon inline ${view ? 'visualize inline hoverable dynamic':''}' ${view ? 'title="'+file.path+'"':''}></div>
								<div class='icon download  inline hoverable dynamic' title="${file.path}"></div>
							</div>
							<div class='file-element' data-url="${file.path}" data-mime="${file.type}">${file.name}</div>
						`
						div.querySelector('.file-element').addEventListener('click', e => e.target.parent('.file-element').toggle('selected'))
						div.icon('visualize')?.addEventListener('click', e => viewFile(...Object.values(e.target.parent('.file-element-wrap').querySelector('.file-element').dataset), 'filepicker'))
						div.icon('download').addEventListener('click', e => downloadFile({'url': e.target.parent('.file-element-wrap').querySelector('.file-element').dataset.url}))

						wrapper.append(div)
					})

					Overlay('filepicker').toggle()
			}

			// show file metadata on unhover display
			Icons('dots').forEach(item => item.onclick = e => e.target.nextElementSibling.toggle())

			// Action for unregistered files
			Button('add-files').onclick = e => {
					pendingAction = {'action': 'insert'}
					selectionGenerator()
			}
			Button('del-files').onclick = e => {
					pendingAction = {'action': 'delete'}
					selectionGenerator()
			}

			Overlay('filepicker').button('validate').onclick = e => {
					const overlay = e.target.parent('overlay')
					const tags  = overlay.querySelectorAll('.file-element[selected]')
					const error = overlay.error('file-choice')

					if (tags.length === 0) {
						error.innerText = 'Sélectionnez au moins un fichier pour continuer';
						error.show()
					} else {
						error.hide()
						overlay.hide()

						filesInserter(tags)
					}
			}
		
		//// FILE UPLOAD
			// update new file
			async function uploader(form) {
						notify("Téléversement en cours", "Le fichier sélectionné est en cours d'ajout sur le serveur.", 'orange')
						
						const adminCheck = Overlay('uploader').input('admin-check').checked
						const file = Overlay('uploader').input('file').files[0]
						const url  = (adminCheck ? 'admin:///files/' : '/files/uploads/') + file.name
						const formData = new FormData(form)
						
						formData.set('mode', 'add')
						formData.set('action', 'upload')
						formData.set('og-url', url)
						formData.set('url', url)
						formData.set('filename', formData.get('filename')+'.'+Input('fileextension').value)
						formData.set('admin', adminCheck)
						if (!adminCheck) formData.set('source', 'uploads/')
						formData.set('file-updated', false)
						formData.set('csrf', csrfToken)

						// Response object
						const response = await fetchAPI('/admin/engine/files', formData)

						if (!response.ok) {// http code 400-500 return false
								notify("Échec du téléversement", `[Erreur HTTP ${response.httpcode}] ${response.body.error}`, 'red');
								return null
								}
						
						if (response.httpcode === 250) {// failed sql request
							errorData = response.body.error
							errorCode = response.body.code
							
							notify("Erreur lors du téléversement", `L'ajout à la base de données a échoué. [Error ${errorCode}]: ${errorData}`, 'red');							
							return
						} else {// correct sql response
							notify("Succès du téléversement", `Le fichier ${response.body.data.path} a été ajouté au serveur et à la base de données`, 'green')
						}

						form.reset()
						Overlay('uploader').child('.selected-file').innerText = ''
						Overlay('uploader').section('frame').hide()
						await updateData()
					}
			
			Icon('upload').onclick = () => Overlay('uploader').toggle()
			
			Overlay('uploader').button('cancel').onclick = e => resetFileUpload(e, true)

			Overlay('uploader').icon('delete').onclick = resetFileUpload

			Overlay('uploader').child('form').onsubmit = e => {
				e.preventDefault()
				e.target.parent('overlay').hide()
				
				uploader(e.target)
			}
		
			// close file viewer
			Overlay('preview').button('close').onclick = e => {
					e.target.parent('overlay').toggle()
					e.target.parent('overlay').section('file-preview').child('img').src = '';
					e.target.parent('overlay').section('file-preview').child('iframe').src = '';
					e.target.parent('overlay').section('file-preview').child('iframe').hide()

					activeOverlay = pendingAction.overlay
					Overlay(activeOverlay)?.toggle()
					pendingAction = {}
			}

			// close unregistered files selector
			Overlay('filepicker').button('cancel').onclick = e => {
					wrapper = e.target.parent('overlay').section('files-list-container')
					Overlay('filepicker').error('file-choice').hide()
					Overlay('filepicker').toggle()
					wrapper.innerHTML = '';
					pendingAction = {}
			}

			// confirm deletion
			Overlay('deletion').button('validate').onclick = e => {
					Overlay('deletion').toggle()
					Overlay('auth').toggle()
					pendingAction['mode'] = Overlay('deletion').input('deletion-mode').checked
			}

			// close deletion control
			Overlay('deletion').button('cancel').onclick = e => {
					e.target.parent('overlay').input('deletion-mode').checked = false;
					transfer(e, false)
			}

			// Auth validation before redirection
			Overlay('auth').child('form').onsubmit = e => e.preventDefault()
			Overlay('auth').button('validate').onclick = async e => {
					validity = await passwordChecker()
					if (validity) transfer(e)
			}

			// close deletion control
			Overlay('auth').button('cancel').onclick = e => {
					Overlay('deletion').input('deletion-mode').checked = false;
					transfer(e, false)
			}

			// handle requested action
			Overlay('validate').button('validate').onclick = e => transfer(e)
			Overlay('validate').button('cancel').onclick = e => transfer(e, false)
		
		//// DATA EDITION
			// show overlay save changes
			Button('save').onclick = e => {
					pendingAction.function = saveCurrentData
					Overlay('auth').show()
			}

			Button('reset').onclick = e => {
					pendingAction.function = resetTabledata
					Overlay('validate').show()
			}
		</script>
	</body>
</html>