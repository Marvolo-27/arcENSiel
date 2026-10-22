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
$authors = array_reduce($raw_authors, function($carry, $item) {if (!in_array($item['author'], $carry)) $carry[] = $item['author']; return $carry;}, []);

// edition mode
$edition ??= false;
$url = $slug ?? '';
if ($edition && $url) {
	$sql = "WITH tags_list AS (
			SELECT article_id, GROUP_CONCAT(tag_id SEPARATOR ',') AS tags
			FROM articles_tags GROUP BY article_id)
	SELECT articles.slug AS url, title, COALESCE(files.url, '/files/placeholder.png') AS picture_path, summary, tags_list.tags AS tags, articles.type AS type, author, date, end_date, color, published_at, articles.updated_at AS updated_at, content
	FROM articles
		JOIN types ON types.id = articles.type
		LEFT JOIN tags_list ON articles.id = tags_list.article_id
		LEFT JOIN articles_covers ON articles.id = articles_covers.article_id
		LEFT JOIN files ON file_id = files.id
	WHERE articles.slug = :url AND NOT (articles.status = 'published')";
	$query=$pdo->prepare($sql);
	$query->execute( [':url' => $url]);
	$artData = $query->fetch(PDO::FETCH_ASSOC);

	if ($artData) {
		// fetch picture metadata
		$query=$pdo->prepare("SELECT url, name, description FROM files WHERE url = :url");
		$query->execute( [':url' => $artData['picture_path']]);
		$fileData = $query->fetch(PDO::FETCH_ASSOC);

		// retrieve article tags
		$tagsIdList = explode(',', $artData['tags']);
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
			
			/* boxes adapted margins - sizes */
			.box.author {margin: 1.5em 0 1em 0}

			#art-author {padding: 0 0 0 .25em; border-radius: 0}
			
			/* metadata section style */
			.extradata {
					font-size: .75em;
					color: var(--metadata-color);
					gap: 1em;
					margin-left: 1em;
					}
			
			/*::-webkit-scrollbar {display: block !important;}*/
			
			.meta-item {
				height: unset !important;
				overflow-x: unset !important;
			}
			.checkboxes {
				flex-wrap: wrap !important;
				overflow-x: unset !important;
			}

			.type-wrap {display: flex; gap: .5em;}
			
			#art-type {
						width: 16em;
						border:none;
						outline:none;
						appearance: base-select;
						align-items: center;
						
						&::picker-icon {transition: rotate .3s;}
						&:open::picker-icon {
							display: flex;
							rotate: 180deg;
							}
						
						&::picker(select) {
							appearance: base-select;
							border-radius: 6px;
							border: none;
							}
						
						& option {
							transition: all .3s;
							display: flex;
							align-items: center;
							gap: .5em;
							padding: .5em .5em;
							border: 1px solid silver;
							width: 16em;
							box-sizing: border-box;
							cursor: pointer;

							&:nth-of-type(1) {display: none;}
							&:nth-of-type(2) {display: none;}
							&:nth-of-type(3) {border-radius: 6px 6px 0 0;}
							&:last-of-type {border-radius: 0 0 6px 6px;}

							:is(&:is(:checked,:hover),selectedcontent) .checkmark {opacity: 1}
							/* &:checked {font-weight: bold} */
							
							&::checkmark {display: none}
							
							:is(&, selectedcontent) .checkmark {
								display: block;
								opacity: 0;
								width: 1em;
								height: 1em;
								border-radius: 50%;
								transition: opacity .25s;
								flex-shrink: 0;
								background: var(--color);
							}
							}
						
						}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="">
				<div class="head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>
					<h1><?= !$edition ? 'Création':'Modification' ?> d'un article</h1>
				</div>
				
				<form id='article-form'>
					<h3>// Métadonnées</h3>
					<div class='metadata'>
						<div class='required-msg'>champs requis</div>
						
						<div class='box url'>
							<div class='input-header'>
								<div class='icon info inline hoverable'>
									<span class='infotext'>
										<?php if (!$edition): ?>Par défaut, l'URL est définie automatiquement à partir du titre de l'article. Cliquez l'icone de modification pour définir une URL personnalisée.<?php endif; ?>
										<?php if ($edition): ?>L'URL n'est pas modifiable pour un article déjà existant.<?php endif; ?>
									</span>
								</div>
								
								<label class='label' for='art-url'>URL de l'article</label>
							</div>
							<div class='input-container'>
								<input class='lineentry livecheck decorated' type='text' name='art-url' id='art-url' autocomplete=off placeholder="URL" value='https://arcensiel.org/articles/<?= $edition ? $artData['url']:'' ?>' title="URL de publication de l'article" required disabled>
								
								<?php if (!$edition): ?><div class="icon edit left hoverable" title="Entrer manuellement l'URL"></div><?php endif; ?>
								<div class="icon validity right"></div>
								<span class="icon spinner right"></span>
							</div>
							<div class='error url'>URL indisponible.</div>
						</div>
						
						<div class='box art-desc'>
							<div class='input-header'>							
								<div class='icon info inline hoverable'>
									<span class='infotext'>
										Description qui apparaîtra sur l'aperçu de l'article
									</span>
								</div>
								
								<label class='label' for='art-desc'>Résumé</label>
							</div>
							<div class='input-container'>
								<input class='lineentry' type='text' name='art-desc' id='art-desc' maxlength="50" autocomplete=of placeholder="Description rapide" value="<?= $edition ? $artData['summary']:'' ?>" title="Résumé rapide l'article pour l'aperçu" required>
							</div>
						</div>
					</div>
					
					<h3>// Contenu</h3>
					<div class='main-info'>
						<div class='box type condensed'>
							<div class='input-container vertical'>	
								<select name='art-type' id='art-type' class='boxentry' required>
									<button><selectedcontent class="centered linear"></selectedcontent></button>
									
									<option value='' <?= !$edition ? 'selected':'' ?> disabled hidden>Type d'évènement</option>
									<?php foreach($typesRaw as $type) { ?>
										<option value='<?= $type['slug'] ?>' <?= ($edition && $artData['type'] === $type['id']) ? 'selected':'' ?> data-color="<?= $type['color'] ?>" <?= $type['slug'] === 'undefined' ? 'disabled hidden':'' ?>>
											<span class="checkmark" style="--color: <?= $type['color'] ?>"></span>
											<span><?= $type['name'] ?><span>
										</option>
									<?php } ?>
								</select>
								<span class="input-container vertical checkboxes">
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
							
							<span class="meta-item tags input-container checkboxes enhanced" id="art-tags">
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
									
									<div class="actions-wrap">
										<div class="action-container">
											<div class='action-btn new-file'>Choisir un nouveau fichier</div>
										</div>
										<div class="action-container">
											<div class='action-btn exist-file'>Choisir un fichier existant</div>
										</div>
									</div>
									
									<div class='frame-overlay rounded centered'>
										<span>Lâcher le fichier pour l'ajouter</span>
										<div class='icon extra-large inline file'></div>
									</div>
								</div>
								
								<input class='fileentry hidden' type='file' accept='image/*' name='art-pic' id='file' data-format="image/*" autocomplete=off placeholder="Affiche" title="Veuillez sélectionner un visuel pour l'évènement" <?= !$edition ? 'required':"data-source-value='".$artData['picture_path']."'" ?>>
								<img class="visual" alt="Affiche de l'évènement" src="<?= $edition ? $fileData['url']:'' ?>" title="Cliquer pour changer d'image">
								<div class="visual-overlay centered linear">
									<div class='icon extra-large inline delete dynamic'></div>
									<div class='icon extra-large inline edit dynamic'></div>
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
					
					<div class='external actions-wrap linear'>
						<div class='action-container'>
							<div class='action-btn publish spinnable' id="publish"><span class='text'><?= $edition ? ($artData['status'] !== 'published' ? 'Sauvegarder':'Publier') : 'Publier' ?></span><div class='spinner'></div></div>
						</div>
						<?php if (false && !$edition):?>
						<div class='action-container'>
							<div class='action-btn draft' id="draft">Brouillon</div>
						</div>
						<?php endif; ?>
						<div class='action-container'>
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
			<div class='icon preview inline large dynamic hoverable' title="Voir l'article"></div>
			<?php if ($edition): ?><div class='icon visualize inline large dynamic hoverable' title="Voir l'article"></div><?php endif; ?>
			
		</div>
		
		<div class="body-overlay" id="overlay-metadata">
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
							<input class='lineentry small' type='text' name='filename' id='filename' value="<?= $edition ? implode('.', explode('.', $fileData['name'], -1)):'' ?>" data-name="<?= $edition ? $fileData['name']:'' ?>" autocomplete=off placeholder="Nom du fichier" title="Nom du fichier" <?= $edition ? 'readonly':'' ?> required>
							<span>.</span>
							<input class='lineentry small' type='text' name='fileextension' id='fileextension' value="<?= $edition ? end(explode('.', $fileData['name'])):'' ?>" autocomplete=off placeholder="ext" title="Extension du fichier" style="width: 5em" required readonly>
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

					<div class='external actions-wrap linear'>
						<div class='action-container'>
							<button class='action-btn validate' type='submit'>Poursuivre</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<?php include($root . '/app/templates/overlay_file-picker.html') ?>
		<?php include($root . '/app/templates/overlay_validation.html') ?>

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		const core = 'https://arcensiel.org/articles/'
		let status = "<?= $edition ? 'edition':'creation' ?>"
		let editsHistory = {}
		let lockedEdit = true
		
		const converter = new showdown.Converter({
											'tables': true,
											'tasklists': true,
											'emoji': true,
											})

	/// Actions management
		// review edits
		Block('article-form').inputs().forEach(elem => elem.onchange = editsManager)
		
		// show panel to save changes
		Button('publish').onclick = e => {
				if (e.target.getAttribute('disabled') !== null) return
				
				Overlay('validate').child('span').textContent = "Enregistrer les modifications ?";
				Overlay('validate').show()
				pendingAction.function = submitForm
		}

		// show panel to save changes
		Button('reset').onclick = e => {
				if (e.target.getAttribute('disabled') !== null) return
				
				if (checkIntegrity()) {
					resetForm()
				} else {
					Overlay('validate').child("span").textContent = "Rétablir le formulaire dans son état d'origine ?";
					Overlay('validate').show()
					pendingAction.function = resetForm
				}
		}

		<?php if ($edition): ?>
		// go to view - validate - edition mode
		Icon('visualize').onclick = e => {
				if (checkIntegrity()) {
					redirect(`/admin/tools/article/preview/<?= $artData['url'] ?>`)
				} else {
					Overlay('validate').child("span").textContent = "Poursuivre et abandonner les modifications non enregistrées ?";
					Overlay('validate').show()
					pendingAction.function = redirect
					pendingAction.link = "/articles/<?= $artData['url'] ?>";
				}
		}
		<?php endif; ?>
		
		// go to preview
		Icon('preview').onclick = async () => {
				url = 'tmp-' + Math.random().toString(16).slice(2)

				formData = generateFormData(Block('article-form'))
				formData.set('action', 'draft')
				formData.set('tmp-url', url)

				await fetchAPI("/admin/engine/article", formData)
				
				open(`/admin/tools/preview/article/${url}`)
		}

		// go on with requested action
		Overlay('validate').button('validate').onclick = e => {
				lockedEdit = false;
				transfer(e)
		}

		// cancel request action
		Overlay('validate').button('cancel').onclick = e => transfer(e, false)
		
		// existing file chosen
		Overlay('filepicker').button('cancel').onclick = e => {
				preview = e.target.parent('overlay').section('file-preview')
				preview.parent('overlay').hide()

				preview.parent('overlay').childs('.file-element').forEach(tag => tag.unset('selected'))
				preview.firstElementChild.src = '';
				preview.firstElementChild.alt = '';
		}

		// Submit form
		function submitForm() {Input('submit').click()}
		
		// Reset form
		function resetForm() {
			Block('article-form').inputs().forEach(elem => {
										elem.value = editsHistory[elem.toSelector(true)].srcValue;
										elem.checked = editsHistory[elem.toSelector(true)].srcChecked;
									})
			Section('visual').src = editsHistory[Section('visual').toSelector(true)].src;
			buttonsToggler(null, true)
		}
		
		function resetEditsHistory() {
			Block('article-form').inputs().forEach(elem => {
					editsHistory[elem.toSelector(true)] = {
							'srcValue': elem.value,
							'value': elem.value,
							'srcChecked': elem.checked,
							'checked': elem.checked,
							}
				})
			editsHistory[Section('visual').toSelector(true)] = {'src': Section('visual').src}
		}

		// check if it is edited
		function checkIntegrity() {
			return Object.values(editsHistory).reduce((bool, item) => bool && item.srcValue === item.value && item.srcChecked === item.checked, true)
		}

		// manage button and edits
		function editsManager(target, input=null) {
				tag = target.target ?? target;	// Allow event or node as argument
				editsHistory[tag.toSelector(true)].value = input === null ? tag.value:input
				editsHistory[tag.toSelector(true)].checked = tag.checked

				buttonsToggler()
		}
		
		// disbale/enable submit buttons
		function buttonsToggler(e, force=null) {
			if ((force === null && checkIntegrity()) || force === true) {
				Button('publish').disable()
				Button('reset').disable()
			} else {
				Button('publish').enable()
				Button('reset').enable()
			}
		}

	/// FORM SUBMISSION ///
		Block('article-form').onsubmit = articleSend
		async function articleSend(e) {
						e.preventDefault()
						if (lockedEdit) return
						lockedEdit = true

						const button = Button('publish')
						const output = Section('output').section('clean')
						const input = Input('art-url')
						
						// spinner in submit button
						buttonsToggler(e, true)
						button.toggle()
						
						const formData = generateFormData(e.target)
						const response = await fetchAPI('/admin/engine/article', formData)

						if (!response.ok) {// http code 400-500 reuturn false
										notify("Erreur lors du téléversement", `Une erreur est survenue lors de l'ajout du fichier sélectionné sur le serveur. [Erreur HTTP ${response.httpcode}] ${response.body.error}`, 'red')
										
										buttonsToggler(e, false)
										button.toggle()
										return null
										}
						
						output.innerHTML = '';
						output.unset('error')
						output.unset('success')
						input.unset('invalid')
						
						if (response.httpcode === 250) {// failed sql request
							errorData = response.body.error
							errorCode = response.body.code
							
							output.innerHTML = `Error [${errorCode}]: ${errorData}`;
							output.set('error')
							input.set('invalid')
							input.setAttribute('oldvalue', urlIn.value);
							
							buttonsToggler(e, false)
							button.toggle()
							notify("Échec de la mise à jour", `La mise à jour de l'article dans la base de données a échoué. [Error ${errorCode}]: ${errorData}`, 'red')
							
							return
						} else {// correct sql response
							artData = response.body.data
						}
						
						// make new tags permanent
						Inputs('checkbox.new').forEach(tag => {
								wrap = tag.parent('.tag-wrap')
								wrap.icon('delete').remove()

								// update keys in editsHistory
								value = editsHistory[tag.toSelector(true)]
								delete editsHistory[tag.toSelector(true)]
								tag.classList.toggle('new')
								editsHistory[tag.toSelector(true)] = value
						})

						output.set('success')
						output.innerHTML = `Success: article ${status === 'edition' ? 'updated':'created'} at url ${artData.url}`;
						resetEditsHistory()

						notify("Modifications enregistrées", "Les données de l'article ont été mises à jour.", 'green')

						buttonsToggler(e, true)
						button.toggle()
					}
		
		function generateFormData(form) {
				const overlay = Overlay('metadata')
				const urlTag  = Input('art-url')
				const picTag  = Input('file')
				const typeTag  = Input('art-type')
				const titleTag = Input('art-title')
				const formData = new FormData(form)
				
				// Update data
				taglist = Array.from(Inputs('checkbox.option:checked')).map(e => ({'slug': e.getAttribute('name'), 'name': e.value}))
				formData.set('art-tags', JSON.stringify(taglist))
				formData.set('file-description', overlay.input('desc').value)
				formData.set('filename', overlay.input('filename').value + '.' + overlay.input('fileextension').value)
				formData.set('type', JSON.stringify({'slug': typeTag.value, 'name': typesMatch[typeTag.value], 'color': typeTag.querySelector(`option[value='${typeTag.value}']`).dataset.color, 'new': typeTag.classList.contains('new')}))
				formData.set('art-url', urlTag.value.replace(core,''))
				picTag.value === ''  ? formData.set('picture-path', picTag.nextElementSibling.getAttribute('src')):null;
				status === 'edition' ? formData.set('pic-updated', picTag.dataset.sourceValue !== picTag.nextElementSibling.getAttribute('src')):null;	// if picture has been edited
				status === 'edition' ? formData.set('og-pic-path', picTag.dataset.sourceValue):null;	// if picture has been edited
				formData.set('status', status)

				return formData
		}

	/// TAGS MANAGEMENT ///
		// add tag input
		let indexer = 0
		function addInput(type, callback) {
				indexer++
				const adder = Input(`new-${type}-option`)

				// allow only one new tage at a time
				if (!!Section('main-info').input(`.${type}-item.new`)) return
				adder.disable()
				
				const span = document.createElement('span')
				span.classList.toggle(`${type}-wrap`)
				span.innerHTML = type === 'tag' ?
				`<input class='lineentry new' type='input' id="newtag${indexer}">`
				:
				`<input class='lineentry new' type='input' id="newtype${indexer}" required>
				<div class='icon colors dynamic inline hoverable livecheck'></div>
				<input class='colorentry hidden' type='color' required>
				<div class='icon validity dynamic inline hoverable'></div>`;
				
				adder.parent('container').insertBefore(span, adder.parentNode)
				span.input('.new').focus()
				span.input('.new').onchange = callback
				
				if (type === 'type') {
					span.icon('colors').onclick = e => e.target.parent('.type-wrap').input('.colorentry').click()
					span.input('.colorentry').oninput = e => e.target.dataset.changed = true
					span.icon('validity').onclick = createType
				}
		}
		Input('new-tag-option').onchange  = e => addInput('tag', createTag)
		Input('new-type-option').onchange = e => addInput('type', createType)
		
		function createTag(e) {
				const adder = Input('new-tag-option')
				const input = e.target
				const wrapper = input.parent('.tag-wrap')
				const value = input.value
				const id = input.id
				
				// check uniqueness
				if (Object.keys(tagsMatch).includes(value.urlForm())) {
					input.set('duplicate')
					return
				}
				input.unset('duplicate')
				
				// add tags
				tagsMatch[value.urlForm()] = value
				
				// update input tag params
				input.type = 'checkbox'
				input.value = value.trim()
				input.name = value.urlForm()
				input.checked = true
				input.classList.toggle('checkbox')
				input.classList.toggle('option')
				input.classList.toggle('lineentry')
				input.onchange = editsManager

				// append label + icon
				wrapper.innerHTML += `<label class='tag-item option orange' for="${id}">${value}</label><div class='icon delete right small dynamic'></div>`;
				
				wrapper.icon('delete').onclick = e => {
							wrap = e.target.parent('.tag-wrap')
							wrap.remove()
							delete editsHistory[wrap.firstElementChild.toSelector(true)]
							delete tagsMatch[wrap.firstElementChild.value]
							buttonsToggler()
						}
				
				// add it to edit editsHistory
				editsHistory[input.toSelector(true)] = {
							'srcValue': input.value,
							'value': input.value,
							'srcChecked': false,
							'checked': false,
							}																	
				editsManager(input)
				adder.enable()
				}
		
		function createType(e) {
				const adder = Input('new-type-option')
				const input = e.target.parent('.type-wrap').input('.lineentry')
				const color = e.target.parent('.type-wrap').input('.colorentry')
				const wrapper = input.parent('box').input('art-type')
				const value = input.value
				const option = document.createElement('option')
				
				// check uniqueness
				if (value === '' || !color.dataset.changed || Object.keys(typesMatch).includes(value.urlForm())) {
					input.set('duplicate')
					return
				}
				input.parent('.type-wrap').remove()

				// add type
				typesMatch[value.urlForm()] = value

				option.classList.toggle('new')
				option.value = value.urlForm()
				option.dataset.color = color.value
				option.innerHTML = `<span class="checkmark" style="--color: ${color.value}"></span>
									<span>${value}<span>`
				option.selected = true
				wrapper.append(option)

				editsManager(option.parentNode)
				adder.enable()
		}

	/// GENERAL ACTIONS ///
		// on load actions
		window.addEventListener('load', loader)
		async function loader()  {
									contentConverter()			// Initial markdown parsing
									resetEditsHistory()			// Set default state
									buttonsToggler()			// Set buttons
									fileSelectorLoad('image')	// Load available images on server
									titleManage(Input('art-title'))
									}
	
	/// INPUT CHECKS ///
		// url maker
		Input('art-title').oninput = titleManage
		function titleManage(e) {
					const title = e.target ?? e // Allow tog or event
					const url = Input('art-url')

					title.innerText.strForm() === '' ? title.set('empty'):title.unset('empty')
					title.nextElementSibling.value = title.textContent
					
					if (url.disabled && status === 'creation') url.value = core + title.textContent.urlForm()
					
					// simulate direct edition
					title.nextElementSibling.dispatchEvent(new Event('change'))
					}
		
		<?php if (!$edition): ?>
		// URL MANAGEMENT //
		function submitAllower(force = false) {Input('submit').disabled = force || Input('art-url').dataset.validity === 'invalid'}

		// check URL validity
		Input('art-title').onblur = e => {if(Input('art-url').disabled) checkInput(Input('art-url'), '/admin/engine/article', 'url', core)}		// trigger check on title change
		Input('art-url').onchange = e => checkInput(e.target, '/admin/engine/article', 'url', core)												// trigger check on URL change
		Input('art-url').oninput  = e => compareWithOldValue(e, core)

		// remove URL protection
		Box('url').icon('edit').onclick = () => Input('art-url').disabled = false
		
		// update tags checkboxes requirement
		Block('art-tags').onclick = () => Inputs('checkbox.option').forEach(tag => tag.required = Inputs('checkbox:checked').length === 0)
		<?php endif; ?>
		
		Input('art-date').onchange = e => Input('art-end-date').setAttribute('min', e.target.value)

		// FILE MANAGEMENT //
		Input('file').onchange = e => {
			fileUploadManage(e)
			editsManager(e.target)
			Overlay('metadata').toggle()
		}
		
		Section('frame').icon('delete').onclick = resetFileUpload
		Section('frame').icon('edit').onclick = () => Overlay('metadata').toggle()
		
		//// EXISTING FILE
		// show file selection panel
		Button('exist-file').onclick = () => Overlay('filepicker').toggle()

		Overlay('filepicker').button('validate').onclick = e => {
					const [url, tag] = filePickManage()
					
					Overlay('filepicker').section('file-preview').firstElementChild.src = '';
					Overlay('filepicker').section('file-preview').firstElementChild.alt = '';
					Box('pic').section('visual').src = url

					editsManager(Box('pic').input('file'), url)
		}

		// file metadata submission
		Overlay('metadata').child('form').onsubmit = e => {e.preventDefault(); e.target.parent('overlay').toggle()}
		
		// filename input
		Overlay('metadata').input('filename').oninput = e => {
				pos = e.target.selectionStart
				e.target.value = e.target.value.filenameForm()
				e.target.setSelectionRange(pos,pos)
				}
		
	/// PREVIEW ///
		// markdown <-> html
		Input('art-content').oninput = e => contentConverter('html')
		
		function contentConverter(direction='html') {
				mdTag = Input('art-content')
				htmlTag = Section('art-content').section('article-content.preview')
				
				if (direction === 'html'){
					md = mdTag.value
					html = converter.makeHtml(md)
					
					htmlTag.innerHTML = html
					
				} else {
					html = htmlTag.innerHTML
					md = converter.makeMarkdown(html)
					
					mdTag.value = md
				}
			}
		</script>
	</body>
</html>