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
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}

// Fetch tags list
$query=$pdo->prepare("SELECT id, name, slug FROM tags");
$query->execute();
$tagsRaw = $query->fetchAll(PDO::FETCH_ASSOC);

// Fetch types list
$query=$pdo->prepare("SELECT id, name, slug, color FROM types");
$query->execute();
$typesRaw = $query->fetchAll(PDO::FETCH_ASSOC) ?? null;

// Authors list
$query=$pdo->prepare("SELECT author FROM articles");
$query->execute();
$raw_authors = $query->fetchAll(PDO::FETCH_ASSOC);

$authors = [];
foreach ($raw_authors as $item) {
	if (!in_array($item['author'],$authors)) {
		$authors[] = $item['author'];
	}
}

// edition mode
$edition = $edition ?? false;
$url = $slug ?? '';
if ($edition && $url) {
	$query=$pdo->prepare("SELECT * FROM articles WHERE url = :url AND NOT (status = 'published')");
	$query->execute( [':url' => $url]);
	$artData = $query->fetch(PDO::FETCH_ASSOC);

	if ($artData) {
		// fetch picture metadata
		$query=$pdo->prepare("SELECT url, name, description FROM files WHERE url = :url");
		$query->execute( [':url' => $artData['picture_path']]);
		$fileData = $query->fetch(PDO::FETCH_ASSOC);

		// retrieve article tags
		$tagsIdList = json_decode($artData['tags'], false);
		$artTags = [];
		foreach ($tagsRaw as $tag) {
			$tag['active'] = in_array($tag['id'], $tagsIdList ?? []) ? true:false;
			$artTags[] = $tag;
		}

		// ARCHIVE OR DRAFT
		$edition_type = $artData['status'];
	} else {
		http_response_code(403);
		echo "403: Edition impossible for published article (/$url)";
		exit;
	}
} else {$artTags = $tagsRaw;}


if ($edition && !$artData) {
	http_response_code(404);
    echo "404: No article exist with specified URL";
    exit;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title><?= !$edition ? 'Création':'Modification' ?> d'un article</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="<?= !$edition ? 'Création':'Modification' ?> d'un article">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/upload.js'></script>
		
		<script type="text/javascript" src="https://unpkg.com/showdown/dist/showdown.min.js"></script>
		
		<style>
			/* article content zone */
			.main-info {
						padding: .75em;
						border: 2px dashed black;
						}
			
			/* custom title margins */
			h3 {margin-top: 2.5em;}
			h1 {margin: 0}
			
			/* icons for metadata */
			.tags::before {background-image: var(--icon-tag);}
			.date::before {background-image: var(--icon-clock);}
			
			/* boxes adapted margins - sizes */
			.box.author {margin: 1.5em 0 1em 0}
			#art-type {
						width: 14em;
						border:none;
						outline:none;
						}
				select::picker {float:left}
			#art-author {padding: 0 0 0 .25em; border-radius: 0}
			
			/* metadata section style */
			.extradata {
					font-size: .75em;
					color: var(--metadata-color);
					gap: 1em;
					margin-left: 1em;
					}

			.output-text.raw {pointer-events: none}

			.input-container.checkboxes span {position: relative;}
			
			::-webkit-scrollbar {display: block !important;}
			
			.meta-item {
				height: unset !important;
				overflow-x: unset !important;
			}
			.checkboxes {
				flex-wrap: wrap !important;
				overflow-x: unset !important;
			}

			.type-wrap {display: flex; gap: .5em;}


			select::picker-icon {transition: rotate .3s;}
			select:open::picker-icon {rotate: 180deg;}

			option {background-color: attr(data-color);}

			#filename, #fileextension {font-size: .7em}
			
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="">
				<div class="head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a>
					<h1><?= !$edition ? 'Création':'Modification' ?> d'un article</h1>
				</div>
				
				<form id='article-form'>
					<h3>// Métadonnées</h3>
					<div class='metadata'>
						<div class='required-msg'>champs requis</div>
						
						<div class='box url'>
							<div class='input-header'>
								<div class='icon info inline hoverable'>
									<span class='img' onclick='event.target.parentNode.toggleAttribute("active")'></span>
									<span class='infotext'>
										<?php if (!$edition): ?>Par défaut, l'URL est définie automatiquement à partir du titre de l'article. Cliquez l'icone de modification pour définir une URL personnalisée.<?php endif; ?>
										<?php if ($edition): ?>L'URL n'est pas modifiable pour un article déjà existant.<?php endif; ?>
									</span>
								</div>
								
								<label class='label' for='art-url'>URL de l'article</label>
							</div>
							<div class='input-container'>
								<input class='lineentry livecheck decorated' type='text' name='art-url' id='art-url' autocomplete=off placeholder="URL" value='https://arcensiel.org/articles/<?= $edition ? $artData['url']:'' ?>' title="URL de publication de l'article" required disabled>
								
								<?php if (!$edition): ?><div class='icon edit left medium hoverable'><span class='img' title="Entrer manuellement l'URL"></span></div><?php endif; ?>
								<div class='icon validity right medium'><span class='img'></span></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error url'>URL indisponible.</div>
						</div>
						
						<div class='box art-desc'>
							<div class='input-header'>							
								<div class='icon info inline hoverable'>
									<span class='img' onclick='event.target.parentNode.toggleAttribute("active")'></span>
									<span class='infotext'>
										Description qui apparaîtra sur l'aperçu de l'article
									</span>
								</div>
								
								<label class='label' for='art-desc'>Résumé</label>
							</div>
							<div class='input-container'>
								<input class='lineentry' type='text' name='art-desc' id='art-desc' maxlength="50" autocomplete=of placeholder="Description rapide" value="<?= $edition ? $artData['description']:'' ?>" title="Résumé rapide l'article pour l'aperçu" required>
							</div>
						</div>
					</div>
					
					<h3>// Contenu</h3>
					<div class='main-info'>
						<div class='box type condensed'>
							<div class='input-container vertical'>	
								<select name='art-type' id='art-type' class='boxentry' required>
									<option value='' <?= !$edition ? 'selected':'' ?> disabled hidden>Type d'évènement</option>
									<?php foreach($typesRaw as $type) { ?>
										<option value='<?= $type['slug'] ?>' <?= ($edition && $artData['type'] === strval($type['id'])) ? 'selected':'' ?> data-color="<?= $type['color'] ?>" <?= $type['slug'] === 'undefined' ? 'disabled hidden':'' ?>><?= $type['name'] ?></option>
									<?php } ?>
								</select>
								<span class="checkboxes">
									<span>
										<input class='checkbox' type='checkbox' id="new-type-option">
										<label class='tag-item new-option' for="new-type-option">+</label>
									</span>
								</span>
							</div>
						</div>
						
						<div class='box title condensed'>
							<div class='input-container'>
								<h1 class="editable" contenteditable placeholder='Titre' id="art-title"><?= $edition ? $artData['title']:'' ?></h1>
								<input class='hidden art-title' name='art-title' value="<?= $edition ? $artData['title']:'' ?>" type="text" required>
							</div>
						</div>
						
						<div class="box extradata">
							<span class="meta-item date input-container">
									<input class='boxentry dateentry' type='date' name='art-date' id='art-date' autocomplete=off placeholder="Date" value="<?= $edition ? $artData['date']:'' ?>" title="Veuillez indiquer la date de l'évènement" required>
									<input class='boxentry dateentry' type='date' name='art-end-date' id='art-end-date' autocomplete=off placeholder="Date" value="<?= $edition ? $artData['end_date']:'' ?>" title="Veuillez indiquer la date de fin de l'évènement">
							</span>
							
							<span class="meta-item tags input-container checkboxes enhanced" id='art-tags'>
								<?php foreach ($artTags as $tag) { ?>
									<span>
										<input class='checkbox option' type='checkbox' id="<?= $tag['slug'] ?>" name="<?= $tag['slug'] ?>" value="<?= $tag['name'] ?>" <?= $edition ? ($tag['active'] ? 'checked':''):null ?>>
										<label class='tag-item option orange' for="<?= $tag['slug'] ?>"><?= $tag['name'] ?></label>
									</span>
								<?php } ?>
									<span>
										<input class='checkbox' type='checkbox' id="new-tag-option">
										<label class='tag-item new-option' for="new-tag-option">+</label>
									</span>
							</span>
						</div>
						
						<div class='box pic'>
							<div class='frame input-container' <?= $edition ? 'active':'' ?>>
								<div class='drag-zone centered'>
									<span>
										<div>Déposez un fichier pour ajouter une illustration à l'article.</div>
										<div><small>Formats supportés : tout type d'image.</small></div>
										<div><small>Taille maximale : 1 Mo</small></div>
									</span>
									
									<div class="form-action-wrap">
										<div class="form-action">
											<div class='action-btn new-file'>Choisir un nouveau fichier</div>
										</div>
										<div class="form-action">
											<div class='action-btn exist-file'>Choisir un fichier existant</div>
										</div>
									</div>
									
									<div class='overlay-panel rounded frame-overlay centered'>
										<span>Lâcher le fichier pour l'ajouter</span>
										<div class='icon extra-large inline file'><span class='img'></span></div>
									</div>
								</div>
								
								<input class='fileentry hidden' type='file' accept='image/*' name='art-pic' id='art-pic' autocomplete=off placeholder="Affiche" title="Veuillez sélectionner un visuel pour l'évènement" <?= !$edition ? 'required':"data-source-value='".$artData['picture_path']."'" ?>>
								<img class="visual" alt="Affiche de l'évènement" src="<?= $edition ? $fileData['url']:'' ?>" title="Cliquer pour changer d'image">
								<div class="visual-overlay">
									<div class='icon extra-large inline delete dynamic' onclick=""><span class='img'></span></div>
									<div class='icon extra-large inline edit dynamic' onclick=""><span class='img'></span></div>
								</div>
							</div>
							<div class='error file'></div>
						</div>
						
						<div class='box art-content shared-layout'>
							<div class='input-container shared-layout-item'>
								<textarea class='textentry' type='text' name='art-content' id='art-content' autocomplete=off placeholder="Contenu de l'article..." title="Contenu de l'article" required><?= $edition ? $artData['content']:'' ?></textarea>
							</div>
							<div class="shared-layout-item">
								<div class='textentry article-content preview'></div>
							</div>
						</div>
					
					
						<div class='box author'>
							<div class='input-container'>
								<span>Par </span>
								<input class='lineentry no-focus' type='text' name='art-author' list='authors-list' id='art-author' autocomplete=off placeholder="Nom" value="<?= $edition ? $artData['author']:'' ?>" title="Veuillez indiquer l'auteur·ice de l'article" required>
								<datalist id='authors-list'>
									<?php foreach ($authors as $author) { ?>
										<option><?= $author ?></option>
									<?php } ?>
								</datalist>
							</div>
						</div>
						
						<?php if ($edition): ?>
							<div class='timestamps'>
								Créé le <?= $artData['published_at'] ?>. Modifié le <?= $artData['updated_at'] ?>
							</div>
						<?php endif; ?>

						<input name='csrf-token' id='csrf' data-csrf='<?= $_SESSION['CSRF'] ?>' value='<?= $_SESSION['CSRF'] ?>' required hidden readonly>
						<input name='submit' id='submit' type="submit" hidden readonly>
					</div>
					
					<div class='external form-action-wrap linear'>
						<div class='form-action'>
							<div class='action-btn publish spinnable' id="publish"><span class='text'><?= $edition ? ($artData['status'] !== 'published' ? 'Sauvegarder':'Publier') : 'Publier' ?></span><div class='spinner'></div></div>
						</div>
						<?php if (false && !$edition):?>
						<div class='form-action'>
							<div class='action-btn draft' id="draft">Brouillon</div>
						</div>
						<?php endif; ?>
						<div class='form-action'>
							<div class='action-btn reset' id="reset">Réinitialiser</div>
						</div>
					</div>
				</form>
				
				<div class='output'>
					<div class='sql-response'>
						<div class='label'>Résultat</div>
						<div class='output-text clean'></div>
					</div>
				</div>
			</div>
		</div>
		
		
		<div id="admin-actions">
			<div class='icon preview inline large dynamic hoverable' title="Voir l'article"><span class='img'></span></div>
			<?php if ($edition): ?><div class='icon visualize inline large dynamic hoverable' title="Voir l'article"><span class='img'></span></div><?php endif; ?>
			
		</div>
		
		<div class="body-overlay" id="overlay-data">
			<div class="inner-overlay">
				<div>
					<h4 class="title">
						Ajouter un fichier
					</h4>
				</div>

				<form id="overlay-add-form">					
					<div class='box filename'>
						<div class='input-header'>
							<label class='label' for='filename'>Nom du fichier</label>
						</div>
						<div class='input-container'>
							<input class='lineentry' type='text' name='filename' id='filename' value="<?= $edition ? implode('.', explode('.', $fileData['name'], -1)):'' ?>" data-name="<?= $edition ? $fileData['name']:'' ?>" autocomplete=off placeholder="Nom du fichier" title="Nom du fichier" <?= $edition ? 'readonly':'' ?> required>
							<span>.</span>
							<input class='lineentry' type='text' name='fileextension' id='fileextension' value="<?= $edition ? end(explode('.', $fileData['name'])):'' ?>" autocomplete=off placeholder="ext" title="Extension du fichier" style="width: 5em" required readonly>
						</div>
					</div>
					
					<div class='box description'>
						<div class='input-header'>
							<label class='label' for='desc'>Description pour le fichier</label>
						</div>
						<div class='input-container'>
							<input class='lineentry' type='text' name='desc' id='desc' value="<?= $edition ? $fileData['description']:'' ?>" autocomplete=off placeholder="Description" title="Description pour le fichier" required>
						</div>
					</div>

					<div class='external form-action-wrap linear'>
						<div class='form-action'>
							<button class='action-btn validate' type='submit'>Poursuivre</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<?php include($root . '/app/templates/overlay_choose.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		const core = 'https://arcensiel.org/articles/'
		let status = "<?= $edition ? 'edition':'creation' ?>"
		let editsHistory = {};
		let pendingAction = {};
		let lockedEdit = true ;
		
		// DOM elements
		const overlayData = document.querySelector('#overlay-data')
		const overlayChoose = document.querySelector('#overlay-choose')
		const overlayValid = document.querySelector('#overlay-valid')


	/// Actions management
		// review edits
		document.querySelectorAll(':is(input, select, textarea)').forEach(elem => {elem.onchange = e => editsManager(e.target)})
		
		// show panel to save changes
		Button('publish').onclick = e => {
				if (e.target.getAttribute('disabled') !== null) return;
				
				overlayValid.child('span').textContent = "Enregistrer les modifications ?";
				overlayValid.show();
				pendingAction.function = submitForm;
		}

		// show panel to save changes
		Button('reset').onclick = e => {
				if (e.target.getAttribute('disabled') !== null) return;
				
				if (checkIntegrity()) {
					resetForm()
				} else {
					overlayValid.child("span").textContent = "Rétablir le formulaire dans son état d'origine ?";
					overlayValid.show();
					pendingAction.function = resetForm;
				}
		}

		<?php if ($edition): ?>
		// go to view - validate - edition mode
		Icon('visualize').onclick = e => {
				btn = e.target.findParent('.icon')
				if (checkIntegrity()) {
					redirect(`/admin/tools/article/preview/<?= $artData['url'] ?>`)
				} else {
					overlayValid.child("span").textContent = "Poursuivre et abandonner les modifications non enregistrées ?";
					overlayValid.show();
					pendingAction.function = redirect;
					pendingAction.link = "/articles/<?= $artData['url'] ?>";
				}
		}
		<?php endif; ?>
		
		// go to preview
		Icon('preview').onclick = async e => {
				btn = e.target.findParent('.icon')
				
				url = 'tmp-' + Math.random().toString(16).slice(2)

				formData = generateFormData(document.querySelector('#article-form'))
				formData.set('action', 'draft')
				formData.set('tmp-url', url)

				await fetchAPI("/admin/engine/article", formData)
				
				open(`/admin/tools/preview/article/${url}`)
		}

		// go on with requested action
		overlayValid.button('validate').onclick = e => {
				lockedEdit = false;
				overlayValid.hide();
				pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function();
				pendingAction = {};
		}

		// cancel request action
		overlayValid.button('cancel').onclick = e => {
				overlayValid.hide();
				pendingAction = {};
		}
		
		// existing file chosen
		overlayChoose.button('cancel').onclick = e => {
				preview = overlayChoose.childs('.file-element')
				overlayChoose.hide();

				preview.forEach(tag => {tag.unset('selected')})
				preview.firstElementChild.src = '';
				preview.firstElementChild.alt = '';
		}

		// Submit form
		function submitForm() {Input('submit').click()}
		
		// Reset form
		function resetForm() {
			document.querySelectorAll(':is(input,select,textarea)').forEach(elem => {
										elem.value = editsHistory[elem.toSelector(true)].srcValue;
										elem.checked = editsHistory[elem.toSelector(true)].srcChecked;
									})
			document.querySelector('.visual').src = editsHistory[document.querySelector('.visual').toSelector(true)].src;
			buttonsToggler(null, true)
		}
		
		function resetEditsHistory() {
			document.querySelectorAll(':is(input,select,textarea)').forEach(elem => {
										editsHistory[elem.toSelector(true)] = {
																				'srcValue': elem.value,
																				'value': elem.value,
																				'srcChecked': elem.checked,
																				'checked': elem.checked,
																				}
									})
			editsHistory[document.querySelector('.visual').toSelector(true)] = {'src': document.querySelector('.visual').src}
		}

		// check if it is edited
		function checkIntegrity() {
			integrity = true;
			Object.values(editsHistory).forEach(val => {
					integrity = integrity && (val.srcValue === val.value && val.srcChecked === val.checked)
			})
			return integrity
		}

		// manage button and edits
		function editsManager(target, input=null) {
					editsHistory[target.toSelector(true)].value = input === null ? target.value:input;
					editsHistory[target.toSelector(true)].checked = target.checked;

					buttonsToggler();
		}
		
		// disbale/enable submit buttons
		function buttonsToggler(e, force=null) {
			if ((force === null && checkIntegrity()) || force === true) {
				Button('publish').disable();
				Button('reset').disable();
			} else {
				Button('publish').enable();
				Button('reset').enable();
			}
		}

	/// FORM SUBMISSION ///
		document.querySelector('#article-form').onsubmit = articleSend;
		async function articleSend(e) {
						e.preventDefault();
						if (lockedEdit) return;
						lockedEdit = true;
						
						// spinner in submit button
						btn = Button('publish');
						buttonsToggler(e, true)
						btn.toggle();
						
						// Raw form data
						formData = generateFormData(e.target)
						
						// Parse in dictionary
						srcdata = Object.fromEntries(formData.entries());
						
						// Response object
						response = await fetchAPI('/admin/engine/article', formData)

						if (!response.ok) {// http code 400-500 reuturn false
										alert(`Error: HTTP ${response.httpcode}: ${response.body.error}`);
										
										buttonsToggler(e, false)
										btn.toggle()
										
										return null
										}
						
						output = document.querySelector('.output .output-text.clean');
						urlIn = Input('art-url');
						
						output.innerHTML = '';
						output.unset('error');
						output.unset('success');
						urlIn.unset('invalid');
						
						if (response.httpcode === 250) {// failed sql request
							errorData = response.body.error;
							errorCode = response.body.code;
							
							output.innerHTML = `Error [${errorCode}]: ${errorData}`;
							output.set('error');
							urlIn.set('invalid');
							urlIn.setAttribute('oldvalue',urlIn.value);
							
							buttonsToggler(e, false)
							btn.toggle()
							notify(`Error [${errorCode}]: ${errorData}`, 'red')
							
							return
						} else {// correct sql response
							artData = response.body['art-data'];
						}
						
						// make new tags permanent
						document.querySelectorAll('.checkbox.new').forEach(tag => {
																					wrap = tag.findParent('.tag-wrap')
																					wrap.icon('delete').remove()

																					// update keys in editsHistory
																					value = editsHistory[tag.toSelector(true)]
																					delete editsHistory[tag.toSelector(true)]
																					tag.classList.toggle('new')
																					editsHistory[tag.toSelector(true)] = value;
																				})

						output.set('success');
						output.innerHTML = `Success: article created successfully at url ${artData.url}`;
						resetEditsHistory()

						notify('Modifications enregistrées', 'green');

						buttonsToggler(e, true)
						btn.toggle();
					}
		
		function generateFormData(form) {
				urlTag = Input('art-url');
				picTag = Input('art-pic');
				typeTag  = Input('art-type');
				titleTag = Input('art-title');
				state = urlTag.disabled;
				urlTag.disabled = false;
				
				// extract form information
				formData = new FormData(form);
				urlTag.disabled = true;
				
				// Update data
				taglist = Array.from(document.querySelectorAll('.checkbox.option:checked')).map(e => {return {'slug': e.getAttribute('name'), 'name': e.value}});
				formData.set('art-tags', JSON.stringify(taglist));
				formData.set('file-description', overlayData.input('desc').value)
				formData.set('filename', overlayData.input('filename').value + '.' + overlayData.input('fileextension').value)
				formData.set('type', JSON.stringify({'slug': typeTag.value, 'name': typesList[typeTag.value], 'color': typeTag.querySelector(`option[value='${typeTag.value}']`).dataset.color, 'new': typeTag.classList.contains('new')}));
				formData.set('art-end-date', formData.get('art-end-date') !== '' ? formData.get('art-end-date'):null);
				// formData.set('art-title', titleTag.textContent);
				formData.set('art-url', formData.get('art-url').replace(core,''));
				(picTag.value === '') ? formData.set('picture-path', picTag.nextElementSibling.getAttribute('src')):null;
				status === 'edition' ? formData.set('pic-updated', picTag.dataset.sourceValue !== picTag.nextElementSibling.getAttribute('src')):null // if picture has been edited
				status === 'edition' ? formData.set('og-pic-path', picTag.dataset.sourceValue):null // if picture has been edited
				formData.set('status', status);

				return formData
		}

	/// TAGS MANAGEMENT ///
		// add tag input
		i_tag = 0;
		function addTag() {
						i_tag++;

						// input linked to 'add' label
						adder = document.querySelector('#new-tag-option');
						
						// allow only one new tage at a time
						assertion = document.querySelector('#art-tags .tag-item.new') === null;
						if (!assertion) {return}
						
						adder.setAttribute('disabled','') // lock add button
						
						span = document.createElement('span');
						span.setAttribute('class','tag-wrap');
						span.innerHTML = `<input class='lineentry new' type='input' id="newtag${i_tag}">`;
						
						adder.findParent('.input-container').insertBefore(span, adder.parentNode);
						span.querySelector('.new').focus()
						
						span.querySelector('.new').onchange = function createTag(e) {
																	// adder button
																	adder = document.querySelector('#new-tag-option');
																	
																	// extract tags
																	tag = e.target;
																	wrapper = tag.findParent('.tag-wrap');
																	
																	// extract user input
																	val = tag.value;
																	
																	// check uniqueness
																	if (Object.keys(tagsList).includes(val.urlForm())) {
																		tag.setAttribute('duplicate','')
																		return
																	}
																	tag.removeAttribute('duplicate')
																	
																	// add tags
																	tagsList[val.urlForm()] = val;
																	
																	adder.removeAttribute('disabled') // unlock button
																	tag.value = tag.value.trim();
																	tag.setAttribute('name', val.urlForm());
																	
																	// update input tag params
																	idt = tag.getAttribute('id');
																	tag.setAttribute('type', 'checkbox')
																	tag.classList.toggle('checkbox');
																	tag.classList.toggle('option');
																	tag.classList.toggle('lineentry');
																	
																	// append label + icon
																	wrapper.innerHTML += `<label class='tag-item option orange' for="${idt}">${val}</label><div class='icon delete right small dynamic'><span class='img'></span></div>`;
																	
																	wrapper.querySelector('.icon.delete').onclick = e => {
																																wrap = e.target.findParent('.tag-wrap');
																																wrap.remove()
																																delete editsHistory[wrap.firstElementChild.toSelector(true)]
																																delete tagsList[wrap.firstElementChild.value]
																																buttonsToggler()
																																}
																	
																	// add it to edit editsHistory
																	editsHistory[tag.toSelector(true)] = {
																				'srcValue': tag.value,
																				'value': tag.value,
																				'srcChecked': false,
																				'checked': false,
																				}
																	
																	neotag = wrapper.firstElementChild;
																	neotag.addEventListener('change', e => editsManager(e.target))
																	neotag.checked = true;
																	editsManager(neotag)
																	};
						}
		document.querySelector('#new-tag-option').onchange = addTag

	// add tag input
		i_type = 0;
		function addType() {
						i_tag++;

						// input linked to 'add' label
						adder = document.querySelector('#new-type-option');
						
						// allow only one new tage at a time
						assertion = document.querySelector('#art-type .type-option.new') === null;
						if (!assertion) {return}
						
						adder.setAttribute('disabled','') // lock add button
						
						span = document.createElement('span');
						span.setAttribute('class','type-wrap');
						span.innerHTML = `<input class='lineentry new' type='input' id="newtype${i_tag}" required><div class='icon colors medium dynamic inline hoverable livecheck'><span class='img'></span></div><input class='colorentry hidden' type='color' required><div class='icon validity medium dynamic inline hoverable'><span class='img'></span></div>`;
						
						adder.findParent('.checkboxes').insertBefore(span, adder.parentNode);
						span.querySelector('.new').focus()
						
						span.icon('colors').onclick = e => e.target.findParent('.type-wrap').querySelector('.colorentry').click()
						span.querySelector('.colorentry').oninput = e => e.target.dataset.changed = true

						span.icon('validity').onclick = function createType(e) {
																	// adder button
																	adder = document.querySelector('#new-type-option');
																	
																	// extract tags
																	tag = e.target.findParent('.type-wrap').querySelector('.lineentry');
																	ctag = e.target.findParent('.type-wrap').querySelector('.colorentry');
																	wrapper = tag.findParent('.input-container').querySelector('#art-type');
																	
																	// extract user input
																	val = tag.value;
																	color = e.target.findParent('type-wrap').querySelector('.colorentry').value;

																	option = document.createElement('option')
																	option.classList.toggle('new')
																	option.setAttribute('value', val.urlForm())
																	option.dataset.color = color;
																	option.innerText = val

																	// check uniqueness
																	if (val === '' || !ctag.dataset.changed || Object.keys(typesList).includes(val.urlForm())) {
																		tag.setAttribute('duplicate','')
																		return
																	}
																	tag.findParent('.type-wrap').remove();
																	
																	// add type
																	typesList[val.urlForm()] = val;
																	
																	// unlock button
																	adder.removeAttribute('disabled') 
																	
																	// append label + icon
																	wrapper.append(option)
																	
																	neotag = option.parentNode;
																	option.selected = true
																	editsManager(neotag)
																	};
						}
		document.querySelector('#new-type-option').onchange = addType
		
		// update tags checkboxes requirement
		document.querySelector('#art-tags').onclick = () => {
					document.querySelectorAll('.checkbox.option').forEach(e => {e.required = document.querySelector('.checkbox:checked') === null})
					}

	/// GENERAL ACTIONS ///
		// on load actions
		window.addEventListener('load', loader)
		async function loader()  {
									contentConverter();

									resetEditsHistory();

									buttonsToggler();

									<?php if (!$edition): ?>titleManage({'target':Input('art-title')})<?php endif; ?>

									fileSelectorLoad()
									}
	
	/// INPUT CHECKS ///
		// url maker
		Input('art-title').oninput = titleManage;
		function titleManage(e) {
					tag = e.target;
					tag.innerText === '\n' || tag.innerText ==='' ? tag.set('empty'):tag.unset('empty')
					tag.nextElementSibling.value = tag.textContent;
					
					urltag = Input('art-url');
					
					(urltag.disabled && status === 'creation') ? urltag.value = core + tag.textContent.urlForm():null;
					
					// simulate direct edition
					tag.nextElementSibling.dispatchEvent(new Event('change'));
					}
		
		<?php if (!$edition): ?>
		// URL MANAGEMENT //
		// url input check
		Input('art-url').oninput = (e) => {
												tag = e.target;
												
												// mark invalid value
												error = tag.findParent('.box').error();
												if (tag.value !== tag.getAttribute('oldvalue')) {
													tag.unset('invalid');
													error.hide();
												} else {
													tag.set('invalid');
													error.show();
												}
												
												// keeps uneditable part safe
												!tag.value.includes(core) ? tag.value = core:null;
											}
				
		
		
		
		// check URL validity
		Input('art-title').onblur = e => {Input('art-url').disabled ? checkURL(e):null;} // trigger check on title change
		Input('art-url').onchange = checkURL		// trigger check on URL change
		async function checkURL(e) {
					urltag = Input('art-url');
					spin = document.querySelector('.input-container:has(#art-url)').spinner();
					btn = Input('submit');
					url = urltag.disabled ? e.target.textContent.replace(core,'').urlForm():e.target.value.replace(core,'').urlForm();
					error = urltag.findParent('.box').error();
					
					urltag.value = core + url;
					urltag.setAttribute('oldvalue',core + url);
					urltag.unset('valid');
					urltag.unset('invalid');
					error.hide();
					spin.show();
					
					// termiante if no url
					if (url === '') return;
					
					// edit mode
					mode = urltag.disabled ? 'locked':'unlocked';
					
					await sleep(500);
					
					response = await fetchAPI(`/admin/engine/article?url=${url}&mode=${mode}&token=${csrfToken}`);
					
					if (!response.ok) {
						urltag.set('invalid');
						btn.disabled = true;
						error.show();
						spin.hide();
					} else {
						urltag.set('valid');
						btn.disabled = false;
						urltag.value = core + response.body.url;
						urltag.unset('oldvalue');
						error.hide();
						spin.hide();
					}
				}
		
		// remove URL protection
		Box('url').icon('edit').onclick = (e) => {
					urltag = e.target.findParent('.input-container').querySelector('.livecheck');
					
					urltag.disabled = false;
					}
		<?php endif; ?>

		// FILE MANAGEMENT //
		Input('art-pic').onchange = e => fileUploadManage(e.target);
		
		visual = document.querySelector('.visual')
		visual.addEventListener('dragenter', allowDrop);
		visual.addEventListener('dragleave', allowDrop);
		
		visual.ondragover = e => {e.preventDefault()}	// fires when a draggable object is over valid drag zone (repeats)
		
		document.querySelector('.frame .action-btn.new-file').onclick = e => {e.target.findParent('.frame').input().click()}	// fires when a draggable object is over valid drag zone (repeats)
		document.querySelector('.frame .visual-overlay').icon('delete').onclick = e => {e.target.findParent('.frame').input().value='';e.target.findParent('.frame').input().required=true;e.target.findParent('.frame').hide();overlayData.child('form').reset()}	// fires when a draggable object is over valid drag zone (repeats)
		document.querySelector('.frame .visual-overlay').icon('edit').onclick = e => overlayData.toggle()
		document.querySelector('.frame .visual').onclick = e => {e.target.findParent('.frame').input().click()}	// fires when a draggable object is over valid drag zone (repeats)
		
		visual.ondrop = fileDropHandle;
		
		function fileDropHandle(e) {
								allowDrop(e);
								
								data = e.dataTransfer;
								files = data.files;
								
								input = Input('art-pic');
								input.files = files;
								fileUploadManage(input, undefined, 10)
								}
		
		// check selected file validity
		function fileUploadManage(input, format=/image\/.*/, maxsize = 2) {
					tag = input;
					
					// extract files info
					files = tag.files;
					if (files.length === 0) {return} // quit if no file selected
					
					// extract file data
					file = files[0];
					
					invalid = '';
					if (files.length > 1) {
						invalid = 'Déposez un seul fichier.';
					} else if (file.type.match(format) === null) {// invalid file type
						invalid = `Type de fichier non supporté : .${file.name.split('.')[file.name.split('.').length - 1]}`;
					} else if (file.size > maxsize*1024*1024) {// invalid file size
						invalid = 'Taille du fichier trop élevée.';
					}
					
					error = Error('file');
					frame = document.querySelector('.frame');
					if (invalid !== '') {
						error.innerText = invalid;
						error.show();
						frame.hide();
						tag.value = '';
						return;
					} else {
						error.hide()
					}
					
					editsManager(tag)
					frame.show();
					Box('pic').child('.visual').src = URL.createObjectURL(file);
					
					// extract from file metadata
					overlayData.input('filename').readOnly = false;
					overlayData.input('filename').value = file.name.filename().filenameForm();
					overlayData.input('filename').dataset.name = file.name.filenameForm();
					overlayData.input('fileextension').value = file.name.extension();
					overlayData.input('desc').value = '';
					

					// display overlay
					overlayData.toggle()
		}
		
		//// EXISTING FILE
		// show file selection panel
		Button('exist-file').onclick = e => {
				overlayChoose.toggle();
		}

		overlayChoose.button('validate').onclick = e => {
				tag = overlayChoose.child('.file-element[selected]')
				error = Error('file-choice')

				if (tag === null) {
					error.innerText = 'Sélectionnez une image pour continuer';
					error.show();
				} else {
					error.hide();
					overlayChoose.hide();

					overlayChoose.childs('.file-element').forEach(tag => {tag.unset('selected')})
					overlayChoose.child('.file-preview').firstElementChild.src = '';
					overlayChoose.child('.file-preview').firstElementChild.alt = '';

					url = tag.dataset.url;
					Box('pic').child('.visual').src = url;
					Box('pic').child('.frame').show();
					Box('pic').child('.fileentry').required = false;

					overlayData.input('filename').dataset.name = url.split('/').slice(-1)[0]
					overlayData.input('filename').value = url.split('/').slice(-1)[0].split('.').slice(0,-1).join('.')
					overlayData.input('fileextension').value = url.split('.').slice(-1)[0];
					overlayData.input('desc').value = tag.dataset.alt;
					overlayData.input('filename').readOnly = true;

					editsManager(Box('pic').child('.fileentry'), url)
				}
		}

		async function fileSelectorLoad() {
				container = overlayChoose.child('.files-list-container');
				container.innerHTML = '<div class="spinner"></div>';

				jsonData = JSON.stringify({'csrf':csrfToken, 'action':'fetch', 'category':'poster'})
				response = await fetchAPI('/admin/engine/files', jsonData)
				
				container.innerHTML = '';

				response.body.forEach(file => {
					wrap = document.createElement('div')
					wrap.classList.toggle('file-element-wrap')

					div = document.createElement('div');

					div.dataset.url = file.url
					div.dataset.alt = file.description
					div.innerText = file.name
					div.classList.toggle('file-element')
					div.addEventListener('click', fileUpdateSelection)

					wrap.append(div)
					container.append(wrap)
				})

		}

		function fileUpdateSelection(event) {
				overlayChoose.childs('.file-element').forEach(tag => {tag.unset('selected')})
				target = event.target;
				preview = overlayChoose.child('.file-preview')

				target.set('selected')
				preview.firstElementChild.src = target.dataset.url
				preview.firstElementChild.setAttribute('alt', target.dataset.alt);
		}

		// file metadata submission
		overlayData.child('form').onsubmit = e => {e.preventDefault();overlayData.toggle()}
		
		// filename input
		overlayData.input('filename').oninput = e => {
				pos = e.target.selectionStart
				e.target.value = e.target.value.filenameForm()
				e.target.setSelectionRange(pos,pos)
				}

		// EDITABLE PARTS MANNAGEMENT //
		converter = new showdown.Converter({
											'tables': true,
											'tasklists': true,
											'emoji': true,
											});
		
		// show placeholder if value is empty
		
		// prevent line breaks in title
		document.querySelector('.title h1').onkeydown = e => {
						if (e.key === 'Enter') {
							e.preventDefault();
						}
						}
		
		// date input requirements
		Input('art-date').onchange = e => Input('art-end-date').setAttribute('min', e.target.value)
		
	/// PREVIEW ///
		// markdown <-> html
		Input('art-content').oninput = e => {contentConverter('html')};
		
		function contentConverter(direction='html') {
				mdTag = Input('art-content')
				htmlTag = document.querySelector('.art-content .article-content');
				
				if (direction==='html'){
					md = mdTag.value;
					html = converter.makeHtml(md);
					
					htmlTag.innerHTML = html;
					
				} else {
					html = htmlTag.innerHTML;
					md = converter.makeMarkdown(html);
					
					mdTag.value = md;
				}
			}
		</script>
	</body>
</html>