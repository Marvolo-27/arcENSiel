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
$query=$pdo->prepare("SELECT url, size FROM files WHERE status = TRUE AND category = 'statuts' AND used_by LIKE '%documents%' ORDER BY updated_at DESC LIMIT 1;");
$query->execute();
$statuts = $query->fetchAll(PDO::FETCH_ASSOC)[0] ?? null;

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
               <p>Dernière mise à jour : <span class="last-updated">17 juin 2025</span></p>

                <p>Consulter les <a href="<?= $statuts['url'] ?>" target="_blank_" download>statuts</a> <small><i>(<?= formatBytes($statuts['size'],1) ?>)</i></small>.</p> 
            
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
						<input name='csrf-token' id='token' data-csrf='<?= $_SESSION['CSRF'] ?>' value='<?= $_SESSION['CSRF'] ?>' required hidden readonly>
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
		
		<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>

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

		<?php include($root . '/app/templates/overlay_choose.html') ?>
		<?php include($root . '/app/templates/overlay_new-file.html') ?>
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

										overlayChoose.querySelector('.file-preview').toggleAttribute('active')
										overlayChoose.querySelector('.fetch-all').toggleAttribute('active')
										}

			// remove a file
			function changeEntryTrigger(tag, action, subcat = 'bm') {
						linkTag = tag.findParent('li').querySelector('a');
						url = linkTag.getAttribute('href'); // .href gives absolute path, so not compatibe with DB format
						name = linkTag.dataset.name
						subcategory = subcat;
						currentFile = {'tag': linkTag, 'url':url, 'subcat': subcat}
						
						if (action === 'delete') {// delete file from list							
							document.querySelector('#overlay-valid .filename').innerText = name;
							document.querySelector('#overlay-valid').toggleAttribute('active')

						} else if (action === 'edit') {// edit pour changer description ou fichier
							document.querySelector('#overlay-add .box.description input').value = linkTag.innerText;
							document.querySelector('#overlay-add .title').innerText = "Modification d'une entrée"
							document.querySelector('#overlay-add .selected-file').innerText = name;
							document.querySelector('#overlay-add .frame').setAttribute('active', '')
							document.querySelector('#overlay-add .fileentry').required = false;
							document.querySelector('#overlay-add').toggleAttribute('active')

							document.querySelector('#overlay-add-form #filename').dataset.name = url.split('/').slice(-1)[0]
							document.querySelector('#overlay-add-form #filename').value = url.split('/').slice(-1)[0].split('.').slice(0,-1).join('.')
							document.querySelector('#overlay-add-form #fileextension').value = url.split('.').slice(-1)[0];
							document.querySelector('#overlay-add-form #filename').disabled = true
							
							mode = 'update';
						}

						return
					}
			
			// on drop logic
			function fileDropHandle(e) {
						allowDrop(e);
						
						data = e.dataTransfer;
						files = data.files;
						
						input = document.querySelector('#file');
						input.files = files;
						
						upload = fileUploadManage(input, /(application\/(pdf|zip|vnd\.sqlite3)|image\/.*)/, 10)
						
						if (upload) {
							document.querySelector('#overlay-add-form #filename').value = file.name.split('.').slice(0,-1).join('.')
							document.querySelector('#overlay-add-form #filename').dataset.name = file.name
							document.querySelector('#overlay-add-form #fileextension').value = file.name.split('.').slice(-1)[0];
						}
					}
			
			// update file description on server
			async function descUpdate(form) {
						url = currentFile.url
						tag = currentFile.tag
						currentFile = {};

						fileTag = document.querySelector('#overlay-add-form .fileentry')

						formData = new FormData(form);
						formData.set('mode', mode)
						formData.set('source', 'uploads/pdf/')
						formData.set('action', 'update')
						formData.set('subcategory', subcategory)	
						formData.set('og-url', url)
						formData.set('url', tag.dataset.targetUrl)
						formData.set('filename', formData.get('filename')+'.'+document.querySelector('#fileextension'))
						formData.set('file-updated', mode === 'update' && url !== tag.dataset.targetUrl)
						formData.set('csrf', document.querySelector('#csrf').dataset.csrf)

						// console.log(Object.fromEntries(formData.entries()))

						// Response object
						response = await fetch("/admin/engine/files", {
									method: "POST",
									body: formData
									});
						
						// Response body content
						data = await response.json(); // dict parsed response data
						// console.log(data)

						if (!response.ok) {// http code 400-500 reuturn false
								alert(`Error: HTTP ${response.status}: ${data.error}`);
								
								return null
								}
						
						if (response.status === 250) {// failed sql request
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

			async function removeElement() {
						url = currentFile.url;
						tag = currentFile.tag;
						subcat = currentFile.subcat;
						currentFile = {};

						// extract stored data to update it accordingly
						jsonData = JSON.stringify({'csrf':document.querySelector('#csrf').dataset.csrf, 'action':'reference', 'mode':'fetch', 'url':url})

						response = await fetch('/admin/engine/files', {
								method: 'POST',
								body: jsonData,
								headers: { 'Content-Type': 'application/json' }
								}
						)
						
						data = await response.json();
						// console.log(data)

						// update used_by
						usage = data.data['used_by'];
						newUsage = usageUpdate('documents/'+subcat, usage)

						// update data on server
						jsonData = JSON.stringify({'csrf':document.querySelector('#csrf').dataset.csrf, 'action':'reference', 'mode':'delete', 'url':url, 'usage':newUsage})

						response = await fetch('/admin/engine/files', {
								method: 'POST',
								body: jsonData,
								headers: { 'Content-Type': 'application/json' }
								}
						)

						if (response.ok) {
							notify('Entry deleted successfully.', 'green');

							tag.parentNode.remove();
						}
					}

			// show panel to save changes
			document.querySelector('.action-btn.new-bm').onclick = e => {
				document.querySelector('#overlay-add #overlay-add-form').reset();
				document.querySelector('#overlay-add .fileentry').required = true;
				document.querySelector('#overlay-add .frame').removeAttribute('active');
				document.querySelector('#overlay-add .title').innerText = "Ajout d'une entrée"
				subcategory = 'bm';
				mode = 'add';
				document.querySelector('#overlay-add').toggleAttribute('active')
			}

			// show panel to save changes
			document.querySelector('.action-btn.new-bf').onclick = e => {
				document.querySelector('#overlay-add #overlay-add-form').reset();
				document.querySelector('#overlay-add .fileentry').required = true;
				document.querySelector('#overlay-add .title').innerText = "Ajout d'une entrée"
				subcategory = 'bf';
				mode = 'add';
				document.querySelector('#overlay-add').toggleAttribute('active')
			}

			document.querySelector('#overlay-add .action-btn.cancel').onclick = e => {
				document.querySelector("#overlay-add").removeAttribute("active");
				document.querySelector('#overlay-add #overlay-add-form').reset();
				document.querySelector("#overlay-add .selected-file").innerText = '';
				document.querySelector('#overlay-add-form #filename').disabled = false
				currentFile = {};
			}

			document.querySelector('#overlay-valid .action-btn.cancel').onclick = e => {
				document.querySelector("#overlay-valid").removeAttribute("active");
				currentFile = {};
			}

			document.querySelector('#overlay-valid .action-btn.validate').onclick = e => {
				document.querySelector("#overlay-valid").removeAttribute("active");
				removeElement()
			}

			document.querySelector('#overlay-add-form').onsubmit = e => {
				e.preventDefault();
				document.querySelector("#overlay-add").removeAttribute("active");
				
				descUpdate(e.target)
			}

			document.querySelector('#overlay-add .icon.delete').onclick = e => {
				document.querySelector("#overlay-add .frame").removeAttribute("active");
				document.querySelector("#overlay-add .selected-file").innerText = '';
				document.querySelector('#overlay-add .fileentry').required = true;
				document.querySelector('#overlay-add #file').value = '';

				document.querySelector('#overlay-add-form #filename').dataset.name = ''
				document.querySelector('#overlay-add-form #filename').value = ''
				document.querySelector('#overlay-add-form #fileextension').value = ''
				document.querySelector('#overlay-add-form #filename').disabled = false
			}

			// cancel request action
			document.querySelector('#overlay-choose .action-btn.cancel').onclick = e => {
				document.querySelector("#overlay-choose").toggleAttribute("active");
				document.querySelector("#overlay-add").toggleAttribute("active");
				document.querySelectorAll('#overlay-choose .file-element').forEach(tag => {tag.removeAttribute('selected')})
				document.querySelector('#overlay-add-form #filename').disabled = false
				//currentFile = {};
			}

			// show file selection panel
			document.querySelector('#overlay-add .action-btn.exist-file').onclick = e => {
					document.querySelector("#overlay-choose").toggleAttribute('active');
					document.querySelector("#overlay-add").toggleAttribute('active');
			}

			document.querySelector('#overlay-choose .validate').onclick = e => {
					tag = document.querySelector('#overlay-choose .file-element[selected]')
					
					if (tag === null) {
						document.querySelector('.error.file-choice').innerText = 'Sélectionnez un fichier pour continuer';
						document.querySelector('.error.file-choice').setAttribute('active', '');
					} else {
						document.querySelector('.error.file-choice').removeAttribute('active');
						document.querySelector('#overlay-choose').removeAttribute('active');
						document.querySelector('#overlay-add').toggleAttribute('active');

						url = tag.dataset.targetUrl;
						Object.keys(currentFile).length !== 0 ? currentFile.tag.dataset.targetUrl = url:currentFile = {'url': null, 'tag': tag}; // prevent error on add mode
						
						document.querySelector('#overlay-add-form #filename').dataset.name = url.split('/').slice(-1)[0]
						document.querySelector('#overlay-add-form #filename').value = url.split('/').slice(-1)[0].split('.').slice(0,-1).join('.')
						document.querySelector('#overlay-add-form #fileextension').value = url.split('.').slice(-1)[0];
						document.querySelector('#overlay-add-form #filename').disabled = true

						document.querySelectorAll('#overlay-choose .file-element').forEach(tag => {tag.removeAttribute('selected')})
						document.querySelector("#overlay-add .selected-file").innerText = url.split('/').slice(-1)[0];
						document.querySelector('#overlay-add .frame').setAttribute('active', '');
						document.querySelector('#overlay-add .fileentry').required = false;
					}
			}

			// FILE MANAGEMENT //
		document.querySelector('#file').onchange = e => {
							upload = fileUploadManage(e.target, /(application\/(pdf|zip|vnd\.sqlite3)|image\/.*)/, 10)
							if (upload) {
								document.querySelector('#overlay-add-form #filename').value = file.name.split('.').slice(0,-1).join('.')
								document.querySelector('#overlay-add-form #filename').dataset.name = file.name;
								document.querySelector('#overlay-add-form #fileextension').value = file.name.split('.').slice(-1)[0];
							}
							};

		async function fileSelectorLoad(category=null) {
				container = document.querySelector('#overlay-choose .files-list-container');
				container.innerHTML = '<div class="spinner"></div>';

				jsonData = JSON.stringify({'csrf':document.querySelector('#token').dataset.csrf, 'action':'fetch', 'category':category})
				response = await fetch('/admin/engine/files', {
						method: 'POST',
						body: jsonData,
						headers: { 'Content-Type': 'application/json' }
						})
				
				data = await response.json();
				
				container.innerHTML = '';

				data.forEach(file => {
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
				document.querySelectorAll('#overlay-choose .file-element').forEach(tag => {tag.removeAttribute('selected')})
				target = event.target;

				target.setAttribute('selected', '')
		}

		overlayChoose.querySelector('.action-btn.fetch-all').onclick = async e => {
				tag = e.target.findParent('.action-btn')
				
				tag.toggleAttribute('active')
				tag.disabled = true
				await fileSelectorLoad()
				tag.toggleAttribute('active')
		}
		<?php endif; ?>
		</script>
    </body>
</html>

