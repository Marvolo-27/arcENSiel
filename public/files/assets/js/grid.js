/// VARIABLES
let nGridItems = 0;
let received = 0;
let total = 0;
let lock = false;
let gridContainer;
let flowContainer;

// set grid layout
window.addEventListener('load', gridDim);
function gridDim()  {
		gridContainer = Block('grid-container')
		flowContainer = Block('flow-container')

		/*if (window.matchMedia(`(min-width: 1268px)`).matches) {
			nGridItems = 5;
		} else */
		if (window.matchMedia(`(min-width: 900px)`).matches) {
			nGridItems = 4;
		} else if (window.matchMedia(`(min-width: 642px)`).matches) {
			nGridItems = 3;
		} else if (window.matchMedia(`(min-width: 412px)`).matches) {
			nGridItems = 2;
		} else {
			nGridItems = 1;
		}
	}

/// GRID GENERATORS
// fetch recent articles
async function fetchNewsFlow(start=0, n=0, script='/engine/news', params='') {
		Spinner().show()
		
		const response = await fetch(`${script}?n=${n}&start=${start}${params}`, {
			headers: { 'X-Requested-With': 'fetch' },
			})
		const data = await response.json()
		
		Spinner().hide()
		
		if (!response.ok) {
			notify("Erreur", "La requête a échoué.", 'red')
			return null
		} else {
			return data
		}			
	}

// auto fetcher - triggered with scroll
async function fetchControl(script=false, params='', mode='grid') {
			const condition = !lock && received < (total || 1) && Section('news-nav-wrap').getBoundingClientRect().y < 0.9 * window.innerHeight;
			
			if (condition) {
				lock = true
				Spinner().show()
			
				// fetch new results
				if (!script) {
					await buildFilesFlow(3*nGridItems)
				} else {
					await buildNewsFlow(received, 2*nGridItems, script, params, mode)
				}

				Spinner().hide()
				lock = false
			}
	}

// Add articles data to DOM
async function buildNewsFlow(start=0, n=5, script='/engine/news', params='', mode='grid') {
		const newsList = await fetchNewsFlow(start, n, script, params)
		const container = mode === 'grid' ? gridContainer:flowContainer

		if (!newsList) return
		
		// extract general data
		received += newsList.metadata.sent
		appended = newsList.metadata.sent
		total = newsList.metadata.total

		// add articles
		newsList.data.forEach((article) => {
				// article data
				url  = article.url;
				desc = article.summary;
				time = article.date != null ? article.date.split(' ')[1]:null;
				day  = article.date != null ? article.date.split(' ')[0]:null;
				auth = article.author;
				tags = article.tags?.split(',')
					tagsHTML = '';
					tags?.forEach((tag, index) => {
						tagsHTML += `<span class="tag-item">
											<a href="/search?&tags=${tagsList[tag].slug}">${tagsList[tag].name}</a>
									</span>`
						if (index !== tags.length -1) tagsHTML += `<span>|</span>`
						})
				title  = article.title;
				type   = article.type;
				slugtype = article.typeslug;
				imgURL = article['picture_path'];
				
				// HTML code for one article
				div = document.createElement('div')
				div.classList.toggle('grid-item-wrap')

				html = mode === 'grid' ?
	`<div class="overlay-metadata" style="background: ${article.color};">
		<h3 class='article-name'>${type}</h3>
		
		<div class="metadata">
			<div class="meta-item illustrated pre date">${day}</div>
					
			<div class="meta-item illustrated pre tags">${tagsHTML}</div>
		</div>
	</div>
	<div class='grid-item'>
		<div class='article-poster poster-bg'>
			<img loading="eager" src="${imgURL}">
		</div>
		
		<div class='article-poster poster-fg'>
			<img src="${imgURL}" data-url="${url}">
		</div>

		<div class="grid-item-preview">
			<h4>${title}</h4>
			
			<span>${desc}</span>
		</div>
	</div>`
	:
	`
	<div class='article-type' style="background: ${article.color};">
		<h3 class='article-name'><a href='/search?&type=${slugtype}'>${type}</a></h3>
	</div>

	<div class='article-item'>
		<div class="header">
			<div class="head-info">
				<h3 class="title">${title}</h1>
			</div>
				
			
			<div class="metadata">
				<?php if ($date): ?>
				<span class="meta-item illustrated date"><?= $date ?><?= $enddate ? " - ".$enddate : null ?></span>
				<?php endif; ?>
				
				<span class="meta-item illustrated pre tags">
					${tagsHTML}
				</span>
			</div>
		</div>
		
		<div class="main">
			<img class="visual" alt="Affiche de l'évènement" src='${imgURL}'>
			<div class='article-description'>${desc}</div>
		</div>

		<div class="view-more">
			<a href="/articles/${url}">Voir plus</a>
		</div>
	</div>`;
				
				// Add to DOM
				div.innerHTML = html;
				container.append(div)

				if (mode === 'grid') div.section('grid-item').onclick = articleGet
		})
		
		// add general data
		plural = received > 1 ? 's':'';
		total > 0 ? document.querySelector('.news-count').innerHTML = `${received} article${plural} affiché${plural} sur ${total}`:null;
		
		return null
	}

// Add articles data to DOM
async function buildFilesFlow(append) {
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
			div.classList.toggle('compact')
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
	<div class='icon dots top left dynamic base-shdw' title="Afficher les métadonnées"></div>
	<div class='icons-bar inverted' data-url='${url}'>
		<div class='icon visualize inline hoverable dynamic invertable' title="Voir le fichier" ></div>
		<div class='icon download inline hoverable dynamic invertable' title="Télécharger le fichier"></div>
		
	</div>
</div>`
				
			// Add to DOM
			div.innerHTML = html
			gridContainer.append(div)
			// add event on click
			if (window.matchMedia(`(hover: none)`).matches) {
				div.section('grid-item').addEventListener('click', e => e.target.parent('.grid-item').section('grid-item-preview').toggle())
			}
			
			div.icon('visualize').addEventListener('click', e => {e.stopPropagation(); viewFile(e.target.parent('.icons-bar').dataset.url)})
			div.icon('download').addEventListener('click', e => {e.stopPropagation(); downloadFile({'url': e.target.parent('.icons-bar').dataset.url})})
			div.icon('dots').addEventListener('click', e => {e.stopPropagation(); e.target.nextElementSibling.toggle()})
		}
		
		// add general data
		plural = received > 1 ? 's':'';
		total > 0 ? document.querySelector('.news-count').innerHTML = `${received} fichier${plural} affiché${plural} sur ${total}`:null;
		
		return null
	}

// go to chosen article
function articleGet(e) {
		const url = e.target.parent('.grid-item').section('article-poster.poster-fg').child('img').dataset.url;
		
		window.location.href = `/articles/${url}`;
	}
