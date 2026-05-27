<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

// Display toast on first login
if (isset($_SESSION['message'])) {
	$message = $_SESSION['message'];
	unset($_SESSION['message']); // remove for future page load
} else {
	$message = false;
}

$artTags = getTags();
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Nos actualités</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Nos actualités">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>

		<style>
			#flow-wrap {
				padding: 1em 0;
			}

			#flow-container {
				display: flex;
				flex-direction: column;
				gap: 5em;
			}

			.article-type {
				padding: .5em .75em;
				border-radius: .25em;
				margin-top: .75em;
				
				& h3 {margin: 0}

				& a {color: unset}
			}

			.article-item {
					padding: 0 2em;
					margin: auto;
					width: 90%;
					box-sizing: border-box;
					display: flex;
					flex-flow: column;
					
				
					&.header, &.footer {
						flex: 0;
						}

					&.mainer {flex: 1}
				}

				.metadata {
						font-size: .9em;
						color: var(--metadata-color);
						margin: .5em 0 .75em 0;

						& a {color: var(--metadata-color)}
						}
				
				.article-description {
						margin: 1em 0;
						}
				
				.visual {
						width: 70%;
						max-width: 400px;
						}
				
					.tags::before {background-image: var(--icon-tag);}
					.date::before {background-image: var(--icon-clock);}
				
				.author {margin: 1.5em 0 1em 0}

				.tags span:last-child {display: none}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id='flow-wrap'>
				<div class="news-header">
					<h1>Nos actualités</h1>
				</div>
				
				<div id="flow-container">
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
		window.onscroll = e => fetchControl()

		// On-load logic
		window.addEventListener('load', loader);
		async function loader(e)  {
									// fetch most recent previews
									await flowBuilder(0, 4);
									fetchControl()
									}
		
		// auto fetcher - triggered with scroll
		async function fetchControl(script='/engine/news', params='') {
						// end of news in viewport
						condition = !lock && received < total && document.querySelector('.news-nav-wrap').getBoundingClientRect().y < 0.9 * window.innerHeight;
						
						if (condition) {
							lock = true
							Spinner().show()
						
							// fetch new results
							await flowBuilder(received, 4, script, params)

							Spinner().hide()
							lock = false
						}
				}

		// Add articles data to DOM
		async function flowBuilder(start=0, n=5, script='/engine/news', params='') {
				newsList = await fetchArticles(start, n, script, params);
				
				// extract general data
				received += newsList.metadata.sent;
				appended = newsList.metadata.sent;
				total = newsList.metadata.total;
				
				// add articles
				newsList.data.forEach((article) => {
						// article data
						url = article.url;
						desc = article.summary;
						time = article.date != null ? article.date.split(' ')[1]:null;
						day = article.date != null ? article.date.split(' ')[0]:null;
						auth = article.author;
						tags = article.tags?.split(',');
							tagsHTML = '';
							tags ? tags.forEach(tag => {
								tagsHTML += `<span class="tag-item">
													<a href="/search?&tags=${tagsMatchTable[tag].slug}">${tagsMatchTable[tag].name}</a>
											</span>
											<span>-</span>`
								}):null;
						title = article.title;
						type = article.type;
						slugtype = article.typeslug;
						imgURL = article['picture_path'];
						
						// HTML code for one article
						div = document.createElement('div');
						div.classList.toggle('article-item-wrap');
						
						html = `
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
							<span class="meta-item date"><?= $date ?><?= $enddate ? " - ".$enddate : null ?></span>
							<?php endif; ?>
							
							<span class="meta-item tags">
								${tagsHTML}
							</span>
						</div>
					</div>
					
					<div class="mainer">
						<img class="visual" alt="Affiche de l'évènement" src='${imgURL}'>
						<div class='article-description'>${desc}</div>
					</div>

					<div class="view-more">
						<a href="/articles/${url}">Voir plus</a>
					</div>
				</div>`
						
						// Add to DOM
						div.innerHTML = html;
						flowContainer.append(div);
				})
				
				return null
			}
		</script>
	</body>
</html>