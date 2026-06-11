<?php
session_start();

// extract passed argument
$raw_url = $slug ?? $_GET['slug'] ?? '';
$status ??= 'published';
$edition ??=  false;

// preview for archived article, draft for unpublished registered article, published for published article, snippet for unpublished unregistered article

// starred article ?
if ($status === 'published' && $_SESSION['user_id']) {
	$query=$pdo->prepare("SELECT user_id FROM bookmarks JOIN articles ON article_id = articles.id WHERE slug = :url AND user_id = :user");
	$query->execute([':user' => $_SESSION['user_id'], ':url' => $raw_url]);
	$starred = $query->fetch();
}


// extract required url
if ((isset($_SESSION['post-data']) && $_SESSION['post-data']['tmp-url'] === $slug) && $status === 'preview') {// preview draft
	$result = $_SESSION['post-data'];
	unset($_SESSION['post-data']);

} elseif ($edition || $status === 'published') {// preview new article
	$sql = "WITH
				tags_list AS (
					SELECT article_id, GROUP_CONCAT(tag_id SEPARATOR ',') AS tags
					FROM articles_tags GROUP BY article_id)
			SELECT articles.slug AS url, title, COALESCE(files.url, '/files/placeholder.png') AS picture_path, summary, tags_list.tags AS tags, types.name AS type, author, date, end_date, color, content
			FROM articles
				LEFT JOIN types ON types.id = COALESCE(articles.type, 0)
				LEFT JOIN tags_list ON articles.id = tags_list.article_id
				LEFT JOIN articles_covers ON articles.id = articles_covers.article_id
				LEFT JOIN files ON file_id = files.id
			WHERE articles.slug = :url and articles.status = :status";
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
$desc = $result['summary'] ?? $result['art-desc'];
$content = $result['content'] ?? $result['art-content'];
$tagsIdList = json_decode('['.$result['tags'].']' ?? '[]', true);
$type = $result['type'] ?? $result['art-type'];
$creation = $result['published_at'] ?? date("Y-m-d H:i:s", time());
$modification = $result['updated_at'] ?? date("Y-m-d H:i:s", time());

// Fetch tags list
$tagsRaw = getTags();

$artTags = $result['art-tags'] ?? [];
if (!empty($tagsIdList)) {
	foreach ($tagsRaw as $tag) {
		in_array($tag['id'], $tagsIdList) ? $artTags[] = $tag:null;
	}
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
		<meta property="og:title" content="<?= $title ?>">
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
				
				/* .title {margin-bottom: 0} */

				.metadata {
						font-size: .9em;
						color: var(--metadata-icons-color);
						margin: .5em 0 .75em 0;

						& a {color: var(--metadata-icons-color)}
						}
				
				.article-content {
						margin-top: 1em;
						}
				
				.visual {
						width: 70%;
						}
				
				.author {margin: 1.5em 0 1em 0}
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div id='article'>
				<div class="header">
					<div class="head-info">
						<?php if($status === 'preview' && $edition): ?><a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a><?php endif; ?>
						<h1 class="title"><?= $title ?></h1>
						<?php if ($_SESSION['user_id'] && $status === 'published'): ?><div class='icon star inline large hoverable' title="<?= $starred ? 'Retirer':'Ajouter' ?> l'article aux favoris" <?= $starred ? 'active':null ?>></div><?php endif; ?>
					</div>
						
					
					<div class="metadata">
						<?php if ($date): ?>
						<span class="meta-item illustrated pre date"><?= $date ?><?= $enddate ? " - ".$enddate : null ?></span>
						<?php endif; ?>
						
						<span class="meta-item illustrated pre tags">
							<?php foreach($artTags as $index => $tag) { ?>
								<span class="tag-item">
										<a href="/search?&tags=<?= $tag['slug'] ?>"><?= $tag['name']?></a>
								</span>
								<?php if ((int) $index !== count($artTags) - 1): ?><span>|</span><?php endif; ?>
							<?php } ?>
						</span>
					</div>
				</div>
				
				<div class="main">
					<img class="visual" alt="Affiche de l'évènement" src='<?= $picture ?: '/files/placeholder.png' ?>'>
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
				<a class='icon edit inline large dynamic' href="/admin/tools/edit/article/<?= $url ?>" title="Modifier l'article"></a>
			<?php endif; ?>
			<?php if(true): ?>
				<div class='icon chg-status <?= $status === 'published' ? 'archive':'unarchive' ?> inline large dynamic hoverable' data-url="<?= $url ?>" title="<?= $status !== 'published' ? 'Publier':'Archiver' ?> l'article"></div>
			<?php endif; ?>
			<input name='csrf-token' id='csrf' data-csrf='<?= $_SESSION['CSRF'] ?>' value='<?= $_SESSION['CSRF'] ?>' required hidden readonly>
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
									}
		
		// handle banner shape
		window.addEventListener('scroll', bannerManage)
		
	<?php if ($_SESSION['user_id'] && $status === 'published'): ?>
		// bookmark managemement
		Icon('star').onclick = markArticle
		async function markArticle(e) {
				const icon = e.target
				const mode = icon.getAttribute('active') !== null ? 'unmark':'mark';
				const url = window.location.pathname.split('/').slice(-1)[0]

				icon.toggle()
			
				const response = await fetch(`/user/engine/bookmarks?action=update&mode=${mode}&url=${url}`, {
						headers: { 'X-Requested-With': 'fetch' },
						})
				const data = await response.json()

				if (!response.ok) {
					notify("Échec de la mise à jour", `Une erreur est survenue lors de ${mode === 'mark' ? "l'ajout":'la suppression'} de l'article ${mode === 'mark' ? 'à':'de'} vos favoris.`, 'red')
					icon.toggle() // reset attribute status
				} else {
					notify("Favoris mis à jour", `L'article '${url}' a été ${mode === 'mark' ? 'ajouté à':'retiré de'} la liste de vos favoris.`, 'green')
				}
		}
	<?php endif; ?>
		
	<?php if($status !== 'preview' && $_SESSION['role'] === 'admin'): ?>
		// handle article archiving
		Block('admin-actions').icon('chg-status').onclick = archiveArticle
		async function archiveArticle(e) {				
				// DOM objects
				const button = e.target
				const toarchive = Array.from(button.classList).includes('archive')
				const state = toarchive ? 'archived':'published';
				const slug = button.dataset.url;

				// Update DOM
				button.classList.toggle('archive')
				button.classList.toggle('unarchive')

				notify(`${toarchive ? 'Archivage':'Publication'} en cours`, `Le statut de l'article ${slug} est en cours de mise à jour.`, 'orange')
				
				// check password
				const jsonData = JSON.stringify({'action':'archive','type':'articles','csrf':csrfToken, 'slug':slug, 'status':state})
				const response = await fetchAPI('/admin/engine/database', jsonData)

				if (!response.ok) {
					notify("Échec de la mise à jour", `Une erreur est survenue lors de ${toarchive ? "l'archivage":'la publication'} de l'article. Veuillez réessayer.`, 'red')
					return
				}

				notify("Statut mis à jour", `L'article ${slug} a été ${toarchive ? 'archivé':'publié'}.`, 'green')
		}
	<?php endif; ?>
		</script>
	</body>
</html>