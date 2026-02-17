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

// Fetch tags list
$query=$pdo->prepare("SELECT id, name, slug FROM tags");
$query->execute();
$artTags = $query->fetchAll(PDO::FETCH_ASSOC) ?? null;
?>

<!DOCTYPE html>
<html>
	<head>
		<title>arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/grid-layout.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/carousel.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/grid.js'></script>
		<script src='/files/assets/js/carousel.js'></script>

		<style>
			#body {background-color: #f0f8ff99;}

		/*!! Intro Banner style !!*/
			#intro {
					height: 70vh;
					font-size: 2em;
					width: 100%;
					background-image: url('/files/images/rainbow_background.jpg');
					background-size: max(100vw, calc(3/2 * 75vh));
					background-position-y: 50%;
					border-bottom: 7.5vh solid var(--bg-color-main);
					display: flex;
					align-items: center;
					justify-content: center;

					@media (min-width: 600px) {font-size: 2.5em}
					@media (min-width: 1000px) {font-size: 3em}
					@media (min-width: 1500px) {font-size: 4em}
					@media (min-width: 2000px) {font-size: 4.5em}

					& .intro-text	 {
							max-width: 60%;

							& p {
								font-family: playball;
								margin: 0;
								text-align: left;
								
								&.title {
										font-size: 150%;
										font-family: mager;
										}

								/* & .accent {font-family: mager;} */
								& .accent {font-family: inherit;}
								}
							}
						}
			
				#main {
						--gbl-pad: 1.5em;
						@media (min-width: 1000px) {--gbl-pad: 3.5em}
						}
			
			.section:not(:first-child) {
					margin-top: 2em;
					
					&::before {
							content: '';
							display: block;
							width: 75%;
							height: .25em;
							background-color: black;
							border-radius: .1875em;
							position: relative;
							left: 12.5%;
							}
					}

			.tags span:last-child {display: none}
			
		#news-carousel {
				--nb: 5 !important; /* works fine if n greater than actaual nb of entries */
				--w: min(75vh, calc(100vw - 2 * var(--gbl-pad)));
				}
		</style>
	</head>	
	
	<body   <?php if ($message): ?>
				data-message="<?= htmlspecialchars($message, ENT_QUOTES) ?>"
			<?php endif; ?>
	>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id="intro">
			<div class="intro-text">
				<p class="title">
					<span class='accent'>arcENSiel</span>
				</p>
				<p>
					association
					<span class='accent'>Queer</span>
					et
					<span class="accent">LGBTQI+</span>
					de l'ENS de Lyon
				</p>
			</div>
		</div>
		
		<div id='main'>		
			<div id='news'>
				<div class="news-header">
					<h1>Nos actualités</h1>
				</div>
				
				<div id='news-carousel' class="carousel-wrap"></div>
				
				<div class="form-action-wrap large">
					<div class='form-action'>
						<button class='action-btn save'><a href='/actualites'>Voir plus</a></button>
					</div>
				</div>

				<div class="spinner"></div>
				<div class="news-count"></div>
			</div>

			<div id="activities" class="section">
				<div class="activities-header">
					<h1>Nos actions</h1>
				</div>

				<div>
					<iframe id="haWidget" allowtransparency="true" src="https://www.helloasso.com/associations/arcensiel-association-queer-de-l-ens-lyon/evenements/drama-queer-7/widget-vignette" style="height:655px;border:none;" onload="window.addEventListener( 'message', function(e) { const dataHeight = e.data.height; const haWidgetElement = document.getElementById('haWidget'); haWidgetElement.height = dataHeight + 'px'; } )"></iframe>
					<iframe id="haWidget" allowtransparency="true" src="https://www.helloasso.com/associations/arcensiel-association-queer-de-l-ens-lyon/boutiques/goodies-arcensiel/widget-vignette" style="height:655px;border:none;" onload="window.addEventListener( 'message', function(e) { const dataHeight = e.data.height; const haWidgetElement = document.getElementById('haWidget'); haWidgetElement.height = dataHeight + 'px'; } )"></iframe>
				</div>

				<div class="form-action-wrap large">
					<div class='form-action'>
						<button class='action-btn save'><a href='https://www.helloasso.com/associations/arcensiel-association-queer-de-l-ens-lyon'>Voir plus</a></button>
					</div>
				</div>
			</div>

			<div class="section">
				<div class="">
					<h1>L'association en quelques mots</h1>
				</div>
				
				<p>
					arcENSiel est une association étudiante et militante qui promeut activement la diversité des genres et des sexualités à l'ENS des Lyon.
					À travers des évènements réguliers, des groupes de discussions, des conférences pédagogiques, des soirées ou encore des Drag Show, nous mettons en lumière la culture Queer et son histoire, tout en continuant de lutter pour nos droits.
					Entre célébration, lutte et pédagogie, arcENSiel est l'interlocutrice privilégiée pour les questions concernant la communauté LGBTQIA+ au sein de l'ENS de Lyon.
				</p>
 
				<p>
					Membre d'un collectif d'associations engagées, nous aspirons aussi à une lutte intersectionnelle plus large contre les injustices et les discriminations.
				</p>

				<div class="form-action-wrap large">
					<div class='form-action'>
						<button class='action-btn save'><a href='https://www.helloasso.com/associations/arcensiel-association-queer-de-l-ens-lyon'>En savoir plus</a></button>
					</div>
				</div>
			</div>
			
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader);
		async function loader()  {
									// PHP sent notifications
									if (body.dataset.message === 'login') {
										notify(`Utilisateurice <?= $message ? $_SESSION['username']:'' ?> connecté·e`, 'green');
										}
									if (body.dataset.message === 'logged-out') {
										notify(`Utilisateurice déconnecté·e`, 'green');
										}
									
									// fetch most recent article
									newslist = await fetchArticles(0, 5)
									data = newslist.data.map(item => {
											return {'src':item['picture_path'], 'url':`/articles/${item.url}`}
										})

									// Generate carousel
									carouselGenerator('#news-carousel', data)
									
									// carousel shift
									carouselShifter()
									}
		</script>
	</body>
</html>