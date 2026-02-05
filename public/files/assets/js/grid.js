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
			gridContainer = document.querySelector('#grid-container');
			flowContainer = document.querySelector('#flow-container');

			if (window.matchMedia(`(hover:hover)`).matches) {
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
			} else if  (window.matchMedia(`(hover:none)`).matches) {
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
		}

/// GRID GENERATORS
// fetch recent articles
async function fetchArticles(start=0, n=0, script='/engine/news', params='') {
			spin = document.querySelector('.spinner');
			
			try {
				spin.setAttribute('active','');
				raw_data = await fetch(`${script}?n=${n}&start=${start}${params}`, {
				// raw_data = await fetch(`/search/search.php?n=${n}&start=${start}${params}`, {
					headers: { 'X-Requested-With': 'fetch' },
					})
				parsed_data = await raw_data.json();// parse response json into array
				spin.removeAttribute('active');
			} catch (e) {
				console.log('Error', e);
				parsed_data = null;
				spin.removeAttribute('active');
			}
			
			return parsed_data
		}

// auto fetcher - triggered with scroll
async function fetchControl(script='/engine/news', params='') {
				// end of news in viewport
				condition = !lock && received < total && document.querySelector('.news-nav-wrap').getBoundingClientRect().y < 0.9 * window.innerHeight;
				
				if (condition) {
					lock = true
					document.querySelector('.spinner').setAttribute('active','')
				
					// fetch new results
					await newsBuilder(received, 2*nGridItems, script, params)

					// document.querySelector('.spinner').removeAttribute('active')
					lock = false
				}
		}

// Add articles data to DOM
async function newsBuilder(start=0, n=5, script='/engine/news', params='') {
		newsList = await fetchArticles(start, n, script, params);
		// console.log(query, newsList)
		
		// extract general data
		received += newsList.metadata.sent;
		appended = newsList.metadata.sent;
		total = newsList.metadata.total;
		
		// add articles
		newsList.data.forEach((article) => {
				// article data
				url = article.url;
				desc = article.description;
				time = article.date != null ? article.date.split(' ')[1]:null;
				day = article.date != null ? article.date.split(' ')[0]:null;
				auth = article.author;
				tags = JSON.parse(article.tags);
					tagsHTML = '';
					tags ? tags.forEach(tag => {
						tagsHTML += `<span class="tag-item">
											<a href="/search?&tags=${tagsMatchTable[tag].slug}">${tagsMatchTable[tag].name}</a>
									</span>
									<span>-</span>`
						}):null;
				// console.log(tagsHTML)
				title = article.title;
				type = article.type;
				imgURL = article['picture_path'];
				
				// HTML code for one article
				div = document.createElement('div');
				div.classList.toggle('grid-item-wrap')
				html = `
	<div class="overlay-metadata" style="background: ${article.color};">
		<h3 class='article-name'>${type}</h3>
		
		<div class="metadata">
			<div class="meta-item date">${day}</div>
					
			<div class="meta-item tags">${tagsHTML}</div>
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
				
				// Add to DOM
				div.innerHTML = html;
				gridContainer.append(div);

				div.querySelector('.grid-item').onclick = articleGet;
		})
		
		// add general data
		plural = received > 1 ? 's':'';
		total > 0 ? document.querySelector('.news-count').innerHTML = `${received} article${plural} affiché${plural} sur ${total}`:null;
		
		return null
	}

// go to chosen article
function articleGet(event) {
		// extract article url
		url = event.target.findParent('.grid-item').querySelector('.article-poster.poster-fg img').dataset.url;
		
		// redirect
		// console.log(`/articles/article.php?slug=${url}`);
		window.location.href = `/articles/${url}`;
	}

// article previews events management
function articleListener() {
		articles = document.querySelectorAll('.grid-item-wrap .grid-item');
		
		articles.forEach(art => {
			// avoid multiple events for same articleblock
			art.removeEventListener('click', articleGet);
			
			art.addEventListener('click', (event) => articleGet(event));
			});
	}
