<?php
session_start();

$query_slug = $query_slug ?? '';

$artTags = getTags();
$artTypes = getTypes();

// Authors list
$query=$pdo->prepare("SELECT DISTINCT author FROM articles WHERE status = 'published'");
$query->execute();
$raw_authors = $query->fetchAll(PDO::FETCH_ASSOC);
$authors = array_map(fn($item) => $item['author'], $raw_authors);


// current filters
$search = $_GET['query'] ?? ''; // text request
$author = $_GET['author'] ?? ''; // author filtering (not exact)
$type = $_GET['type'] ?? null; // type filttering (extact)
$from = $_GET['from'] ?? null; // low-limit date
$to = $_GET['to'] ?? null; // high-limit date
$tags = $_GET['tags'] ?? null;
$tags = $tags ? explode(',', $tags) : [null];

$arguments = ($type || $author || $from || $to || $tags !== [null] || $query);
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Recherche</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Recherche">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>

		<style>
			.box.search {
					gap: .5em;
					
					& .input-container {
						align-items: center;
						gap: .5em;
						flex: 1 0;
						}
			}

			.filters-wrap {
					display: flex;
					flex-flow: row;
					gap: .75em;

					@media (min-width: 850px) {height: 4.5em}

					@media (min-width: 1000px) {
							--offset: 20vw;
						    width: calc(100vw - 2* var(--offset));
							position: relative;
    						left: calc(-0.5 * (100vw - 1000px) - 2em - 1em + min(100vw - 1000px, var(--offset)));
							}

					&[active] #filters {display: flex  !important;}

					& .icon.filters {
							flex-shrink: 0;
							}
					
					& #filters {
							display: none;
							flex-flow: column;
							align-items: flex-start;
							gap: .375em;
							overflow-x: auto;

							@media (min-width: 850px) {
									overflow-y: hidden;
									flex-flow: row;
									align-items: center;
									gap: .75em;
									}

							& .input-container {
									align-items: center;
									gap: .5em;
									
									@media (min-width: 850px) {
											align-items: flex-start;
											flex-direction: column;
											height: 4.25em;
											}

									.header:has(.checkbox:checked) ~ .content {
												display: flex;
											}
									
									& .content {
											display: none;
											align-items: center;
											flex-flow: row;
											gap: .5em;
											font-size: .75em;
											
											&[active] {display: flex;}
											
											& span {
													display: flex;
													gap: .5em;
													align-items: center;
													}
											}
									}
							}
					}
			
			.tags span:last-child {display: none}
			::-webkit-scrollbar {display: none !important;}
		</style>
	</head>
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<form id="search-form" action="/engine/search">
				<div class='box search linear'>
					<div class='icon filters inline large hoverable dynamic' <?= ($type || $author || $from || $to || $tags !== [null]) ? 'active':'' ?>><span class='img'></span></div>
					<div class='input-container'>
						<div class='icon search left medium'><span class='img'></span></div>
						<input class='lineentry decorated' type='search' name='query' value="<?= $search ?>" id='textsearch' value="<?= $query_slug ?>" autocomplete=on <?= ($type || $author || $from || $to || $tags !== [null]) ? '':'required' ?> placeholder="Recherche...">
					</div>
					<button class='icon send large inline dynamic hoverable' type='submit'><span class='img'></span></button>
				</div>
				
				<div class="filters-wrap">					
					<div id="filters">
						<div class="box tags condensed">
							<div class="input-container">
								<div class="header">
									<input class='checkbox header' type='checkbox' name='tags-wrap' id='tags-wrap' <?= $tags !== [null] ? 'checked':'' ?>>
									<label class='tag-item option blue' for="tags-wrap">Tags</label>
								</div>
								<div class="content">
									<?php foreach ($artTags as $tag) { ?>
										<span>
											<input class='checkbox option filter-item' type='checkbox' id="<?= $tag['slug'] ?>" name="<?= $tag['slug'] ?>" value="<?= $tag['name'] ?>" <?= $arguments ? '':'required' ?> <?= in_array($tag['slug'], $tags) ? 'checked':'' ?>>
											<label class='tag-item option orange' for="<?= $tag['slug'] ?>"><?= $tag['name'] ?></label>
										</span>
									<?php } ?>
								</div>
							</div>
						</div>

						<div class="box author condensed">
							<div class="input-container">
								<div class="header">
									<input class='checkbox header' type='checkbox' name='author-wrap' id='author-wrap' <?= $author ? 'checked':'' ?>>
									<label class='tag-item option blue' for="author-wrap">Auteurice</label>
								</div>
								<div class="content">
									<input class='lineentry filter-item' type='text' name='art-author' list='authors-list' value="<?= $author ? $author:'' ?>" id='art-author' autocomplete=off placeholder="Nom" title="Veuillez indiquer l'auteur·ice de l'article" <?= $arguments ? '':'required' ?>>
									<datalist id='authors-list'>
										<?php foreach ($authors as $author) { ?>
											<option><?= $author ?></option>
										<?php } ?>
									</datalist>
								</div>
							</div>
						</div>

						<div class="box time condensed">
							<div class="input-container">
								<div class="header">
									<input class='checkbox header' type='checkbox' name='date-wrap' id='date-wrap' <?= ($from || $to) ? 'checked':'' ?>>
									<label class='tag-item option blue' for="date-wrap">Date</label>
								</div>
								<div class="content">
									<span>
										De <input class='boxentry dateentry filter-item' type='date' name='art-date-from' value="<?= $from ? $from:'' ?>" id='art-date-from' autocomplete=off placeholder="De" title="Veuillez indiquer la date de l'évènement" <?= $arguments ? '':'required' ?>>
									</span>
									<span>
										À <input class='boxentry dateentry filter-item' type='date' name='art-date-to' value="<?= $to ? $to:'' ?>" id='art-date-to' autocomplete=off placeholder="À" title="Veuillez indiquer la date de l'évènement" <?= $arguments ? '':'required' ?>>
									</span>
								</div>
							</div>
						</div>
						
						<div class='box type condensed'>
							<div class='input-container'>	
								<div class="header">
									<input class='checkbox header' type='checkbox' name='type-wrap' id='type-wrap' <?= $type ? 'checked':'' ?>>
									<label class='tag-item option blue' for="type-wrap">Type</label>
								</div>
								<div class="content">
									<select name='type' id='art-type' class='boxentry filter-item' <?= $arguments ? '':'required' ?>>
										<option value='' selected disabled hidden></option>
										<?php foreach($artTypes as $item) { ?>
											<option value='<?= $item['slug'] ?>' <?= ($type === $item['slug']) ? 'selected':'' ?> data-color="<?= $item['color'] ?>"><?= $item['name'] ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
			
			<div id='grid-wrap'>
				<div class="news-header">
					<h2></h2>
				</div>
				
				<div id="grid-container">
				</div>
				
				<div id="news-footer">
					<div class="news-nav-wrap">
						<div class="spinner"></div>
						<div class="news-count"></div>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader);
		async function loader()  {
									triggerSearch()
									}
		
		// update inputs requirement
		document.querySelectorAll('#search-form :is(input, select):not(.header)').forEach(item => item.onchange = e => {
							unmodified = true;
							document.querySelectorAll('#search-form :is(input, select):not(.header)').forEach(it => {it = it.nodeName === 'LABEL' ? it.previousElementSibling:it;unmodified = unmodified && (it.type === 'checkbox' ? !it.checked : it.value === '')})
							document.querySelectorAll('#search-form :is(input, select):not(.header)').forEach(it => {it = it.nodeName === 'LABEL' ? it.previousElementSibling:it; it.required = unmodified});
							})
		
		// date input requirements
		Input('art-date-from').oninput = e => Input('art-date-to').setAttribute('min', e.target.value)

		// search submission
		window.onscroll = e => fetchControl()

		async function fetchControl() {
				// end of news in viewport
				condition = !lock && received < total && document.querySelector('.news-nav-wrap').getBoundingClientRect().y < 0.95 * window.innerHeight;
				
				if (condition) {
					lock = true
					
					// fetch new results
					await triggerSearch()

					lock = false
				}
		}


		document.querySelector('#search-form').onsubmit = fetchSearch;
		async function fetchSearch(e) {
			e.preventDefault();

			formData = new FormData(e.target)
			data = {};

			taglist = Array.from(document.querySelectorAll('.tags .content .checkbox.option:checked')).map(e => e.getAttribute('name'));
			
			data.query = formData.get('query') !== '' ? formData.get('query') : false
			data.author = (formData.get('art-author') !== '' && document.querySelector('#author-wrap').checked) ? formData.get('art-author') : false
			data.from = (formData.get('art-date-from') !== '' && document.querySelector('#date-wrap').checked) ? formData.get('art-date-from') : false
			data.to = (formData.get('art-date-to') !== '' && document.querySelector('#date-wrap').checked) ? formData.get('art-date-to') : false
			data.type = (formData.get('type') !== null && document.querySelector('#type-wrap').checked) ? formData.get('type') : false;
			data.tags = document.querySelector('#tags-wrap').checked ? JSON.stringify(taglist) : '[]';
			
			params = '';
			params += data.query ? '&query='+data.query:'';
			params += data.author ? '&author='+data.author:'';
			params += data.type ? '&type='+data.type:'';
			params += data.from ? '&from='+data.from:'';
			params += data.to ? '&to='+data.to:'';
			params += data.tags !== '[]' ? '&tags='+data.tags.replaceAll('"','').slice(1,-1):'';
			n = 4;

			window.location.href = `/search?${params}`
		}
		
		
		// Trigger search
		async function triggerSearch(update=true) {
				// extract parameters, given manually or through form submission
				queries = window.location.search.slice(1) // GET query, w/o ?

				// fetch most recent arfticles matching given parameters
				Spinner().show()
				queries !== '' ? await newsBuilder(received, 3 * nGridItems, '/engine/search', queries):null;
				Spinner().hide()
				
				text = queries !== '' ? `${total} Résultat${total>1?'s':''}` : 'Faites une requête pour effectuer une recherche';
				update ? document.querySelector('.news-header h2').textContent = text:null;

				return null
				}

		// filters panel management
		Icon('filters').onclick = e => {e.target.findParent('#search-form').child('.filters-wrap').toggle()};
		</script>
	</body>
</html>