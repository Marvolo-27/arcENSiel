<?php
session_start();

// extract passed argument
$raw_url = $slug ?? $_GET['slug'];
$raw_url = $raw_url ?? '';
$status = $status ?? 'published';
$edition = $edition ?? false;

// preview for archived article, draft for unpublished registered article, published for published article, snippet for unpublished unregistered article

// starred article ?
if ($status === 'published' && $_SESSION['user_id']) {
	$query=$pdo->prepare("SELECT user_id FROM bookmarks JOIN articles ON article_id = articles.id WHERE url = :url AND user_id = :user");
	$query->execute([':user' => $_SESSION['user_id'], ':url' => $raw_url]);
	$starred = $query->fetch();
}


// extract required url
if ((isset($_SESSION['post-data']) && $_SESSION['post-data']['tmp-url'] === $slug) && $status === 'preview') {// preview draft
	$result = $_SESSION['post-data'];
	unset($_SESSION['post-data']);

} elseif ($edition || $status === 'published') {// preview new article
	$sql = "SELECT url, title, picture_path, description, tags, types.name AS type, author, date, end_date, color, content FROM articles JOIN types ON types.id = CAST(articles.type AS SIGNED INTEGER) WHERE url = :url and status = :status";
	$query = $pdo->prepare($sql);
	$query->execute([':url' => $raw_url, ':status' => $edition ? 'archived':'published']);
	$result = $query->fetch(PDO::FETCH_ASSOC);

	if (!$result) {
		http_response_code(404);
		echo "404: URL '$raw_url' not found on server with status $status.";
		return;
	}
}

// article data
$url = $result['url'] ?? $result['art-url'];
$title = $result['title'] ?? $result['art-title'];
$author = $result['author'] ?? $result['art-author'];
$picture = $result['picture_path'] ?? $result['picture-path'];
$date = $result['date'] ?? $result['art-date'] ?? null;
$enddate = $result['end_date'] ?? $result['art-end-date'] ?? null;
$desc = $result['description'] ?? $result['art-desc'];
$content = $result['content'] ?? $result['art-content'];
$tagsIdList = json_decode($result['tags'] ?? $result['art-tags'] ?? '[]', true);
$type = $result['type'] ?? $result['art-type'];
$creation = $result['published_at'] ?? date("Y-m-d H:i:s", time());
$modification = $result['updated_at'] ?? date("Y-m-d H:i:s", time());

// Fetch tags list
$query=$pdo->prepare("SELECT id, name, slug FROM tags");
$query->execute();
$tagsRaw = $query->fetchAll(PDO::FETCH_ASSOC);

$artTags = [];
if ($status === 'preview' && $edition) {
	foreach ($tagsRaw as $tag) {
		in_array($tag['id'], $tagsIdList) ? $artTags[] = $tag:null;
	}
} else {
	$artTags  = $tagsIdList;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title><?= $title ?></title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		
		<script type="text/javascript" src="https://unpkg.com/showdown/dist/showdown.min.js"></script>
		
		<style>
			/*!! News !!*/
			#article {
					padding: 1em;
					margin: auto;
					width: 90%;
					box-sizing: border-box;
					display: flex;
					flex-flow: column;
					}
				
				
				.header, .footer {
						flex: 0;
						}
				
				.mainer {flex: 1}
				
				/* .title {margin-bottom: 0} */

				.metadata {
						font-size: .9em;
						color: var(--metadata-color);
						margin: .5em 0 .75em 0;

						& a {color: var(--metadata-color)}
						}
				
				.article-content {
						margin-top: 1em;
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
			<div id='article'>
				<div class="header">
					<div class="head-info">
						<?php if($status === 'preview' && $edition): ?><a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a><?php endif; ?>
						<h1 class="title"><?= $title ?></h1>
						<?php if ($_SESSION['user_id'] && $status === 'published'): ?><div class='icon star inline large hoverable' title="<?= $starred ? 'Retirer':'Ajouter' ?> l'article aux favoris" <?= $starred ? 'active':null ?>><span class='img'></span></div><?php endif; ?>
					</div>
						
					
					<div class="metadata">
						<?php if ($date): ?>
						<span class="meta-item date"><?= $date ?><?= $enddate ? " - ".$enddate : null ?></span>
						<?php endif; ?>
						
						<span class="meta-item tags">
							<?php foreach($artTags as $tag) { ?>
								<span class="tag-item">
										<a href="/search?&tags=<?= $tag['slug'] ?>"><?= $tag['name']?></a>
								</span>
								<span>|</span>
							<?php } ?>
						</span>
					</div>
				</div>
				
				<div class="mainer">
					<img class="visual" alt="Affiche de l'évènement" src='<?= $picture ?>'>
					<div class='article-content'>
					</div>
				</div>

				<div class="footer">
					<div class="author">Par <a href="/search?author=<?= $author ?>"><?= $author ?></a></div>

					<div class='timestamps'>
						Créé le <?= $creation ?>. Modifié le <?= $modification ?>.
					</div>
				</div>
			</div>
		</div>
		
		<?php if($_SESSION['role'] === 'admin'): ?>
		<div id="admin-actions">
			<?php if($status === 'preview' && $edition): ?>
				<a class='icon edit inline large dynamic' href="/admin/tools/article/edit/<?= $url ?>" title="Modifier l'article">
					<span class='img'></span>
				</a>
			<?php endif; ?>
			<?php if(true): ?>
				<div class='icon chg-status <?= $status === 'published' ? 'archive':'unarchive' ?> inline large dynamic hoverable' data-url="<?= $url ?>" title="<?= $status !== 'published' ? 'Publier':'Archiver' ?> l'article">
					<span class='img'></span>
				</div>
			<?php endif; ?>
			<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>
		</div>
		<?php endif; ?>
		

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// remove useless elements
		window.addEventListener('load', loader)
		async function loader()  {
									converter = new showdown.Converter();
									tag = document.querySelector('.article-content');
									html = converter.makeHtml(<?= json_encode($content) ?>);
									
									tag.innerHTML = html;

									console.log("<?= $from ?>", '\n', "<?= $to ?>")
									}
		
		// handle banner shape
		window.addEventListener('scroll', bannerManage)
		
		<?php if ($_SESSION['user_id'] && $status === 'published'): ?>
		// bookmark managemement
		document.querySelector('.icon.star').onclick = markArticle
		async function markArticle(e) {
				tag = e.target.findParent('.icon');
				
				mode = tag.getAttribute('active') !== null ? 'unmark':'mark';
				url = window.location.pathname.split('/').slice(-1)[0];
				tag.toggleAttribute('active')
			
				response = await fetch(`/user/bookmarks-engine.php?action=update&mode=${mode}&url=${url}`, {
						headers: { 'X-Requested-With': 'fetch' },
						})
				data = await response.json()

				if (!response.ok) {
					notify('Erreur lors de la mise à jour des favoris.', 'red')
					tag.toggleAttribute('active') // reset attribute status

					console.log(data)
				} else {
					notify('Favoris mis à jour', 'green')
				}
		}
		<?php endif; ?>

		// handle article archiving
		async function archiveArticle(event) {
				notify('Mise à jour du statut', 'orange')
				
				// csrf token
				token = document.querySelector('#csrf').dataset.csrf;
				
				// dom objects
				btn = event.target.findParent('.icon')
				toarchive = Array.from(btn.classList).includes('archive');

				// extract data
				state = toarchive ? 'archived':'published';
				slug = btn.dataset.url;

				await sleep(500);
				
				jsonData = JSON.stringify({'action':'archive','type':'articles','csrf':document.querySelector('#csrf').dataset.csrf, 'slug':slug, 'status':state})
				//console.log(jsonData)
				
				// check password
				response = await fetch('/admin/engine/database', {
										method: 'POST',
									    body: jsonData,
									    headers: { 'Content-Type': 'application/json' }
									    }
									)
				data = await response.json();

				if (!response.ok) {
					notify("Action failed.", 'red')
					return
				}

				// Update DOM and table data
				btn.classList.toggle('archive')
				btn.classList.toggle('unarchive')

				notify('Statut mis à jour avec succès', 'green')
		}
		<?php if($status !== 'preview'): ?>
			document.querySelector('#admin-actions .icon.chg-status').onclick = archiveArticle;
		<?php endif; ?>

		// auth form managemement
		document.querySelector('.nav-logo.login').onclick = () => {if (window.matchMedia('(hover: none)').matches) {authWrap()}};
		document.querySelector('.auth-wrap .icon.cross .img').onclick = () => {authWrap()};
		try {
			document.querySelector('.toggle-pass').onclick = () => {togglePassword('#pswd')};
		} catch (e) {null}
		</script>
	</body>
</html>