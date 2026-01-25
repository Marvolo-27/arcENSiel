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
		<link rel='stylesheet' type='text/css' href='/files/assets/css/index.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/articles-grid.js'></script>

		<style>
			#body {background-color: #f0f8ff99;}

		/*!! Intro Banner style !!*/
			#intro {
					height: 75vh;
					font-size: 2em;
					width: 100%;
					background-image: url('/files/images/rainbow_background.jpg');
					background-size: max(100vw, calc(3/2 * 75vh));
					background-position-y: 50%;
					border-bottom: 7.5vh solid var(--main-color-dark);

					@media (min-width: 600px) {font-size: 2.5em}
					@media (min-width: 1000px) {font-size: 3em}
					@media (min-width: 1500px) {font-size: 4em}
					@media (min-width: 2000px) {font-size: 4.5em}

					& .intro-text	 {
							margin: 25vh auto;
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
						
						background-color: #1616c10a;
						padding: 2em var(--gbl-pad);
						/* box-shadow: 0 0 5px 0 darkblue; */
						
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
			
		/*!! Carousel style !!*/
			.carousel-wrap	{
					--h: 30vh;
					--w: min(55vh, calc(100vw - 2 * var(--gbl-pad)));
					/* --ws: calc(0.8 * var(--w)); width shrunk */
					--ws: calc(var(--w) - 2 * (var(--standard-size) * (var(--coeff) + 2 * .2) + var(--gap) + var(--pad))); /* account for gaps, icons width and padding, carousel padding */
					--pad: .675em;
					--gap: .5em;
					--coeff: 2.25; /* extra large icons */

					display: flex;
					align-items: center;
					height: var(--h);
					z-index: 1;
					top: calc(0.5*(100% - var(--h)));
					left: 5%;
					width: var(--w);
					margin: 0 calc(1/2 * (100% - var(--w)));
					margin-bottom: 1.5em;
 					justify-content: center;
					gap: var(--gap);

					& .prev {transform: rotate(180deg); flex-shrink: 0;}
					& .next {transform: rotate(0deg); flex-shrink: 0;} 
					
					.carousel {
							overflow: hidden;
							width: calc(var(--ws) + 2 * var(--pad));
							z-index: 1;
							padding: calc(2 * var(--pad)) var(--pad);
							box-sizing: border-box;
							border-radius: 1.25em;
							background-color: #dcf5ea;
							--lbl-clr: #ff71f2;
							
							.carousel-inputs {display: none}

							.carousel-lbls {
									display: flex;
									align-items: center;
									justify-content: center;
									gap: .5em;
									margin-top: 1em;

									& label {
											display: inline-block;
											width: .7em;
											aspect-ratio: 1;
											border-radius: 50%;
											background-color: #777;
											cursor: pointer;

											@media (min-width: 1000px) {width: 1em}
											}
									}
							
							.carousel-imgs {
									width: calc(var(--nb) * var(--ws));
									display: flex;
									height: calc(0.825 * var(--h));
									transition: all .3s ease-in-out;

									& .carousel-img-wrap {
											width: calc(var(--ws));
											display: flex;
											justify-content: center;
											border-radius: 1.25em;
											
											& img {
													max-width: calc(var(--ws) - 2.1 * var(--pad)); /* pour qu'il n'y ait pas d'overflow au niveau du padding du carousel lors de la translation */
													cursor: pointer;
													transition: all .2s;

													&:hover {box-shadow: 0 0 12px 0 #222;}
													}
											}
									}
							}
					}
			#news-carousel {--nb: 5;} /* works fibe if n greater than actaual nb of entries */
			
	
			/* JS addition */
			.carousel:has(#img1:checked) {.carousel-imgs {transform: translateX(calc(-0*(var(--ws))))}  .img1 {background-color: var(--lbl-clr)}}
			.carousel:has(#img2:checked) {.carousel-imgs {transform: translateX(calc(-1*(var(--ws))))}  .img2 {background-color: var(--lbl-clr)}}
			.carousel:has(#img3:checked) {.carousel-imgs {transform: translateX(calc(-2*(var(--ws))))}  .img3 {background-color: var(--lbl-clr)}}
			.carousel:has(#img4:checked) {.carousel-imgs {transform: translateX(calc(-3*(var(--ws))))}  .img4 {background-color: var(--lbl-clr)}}
			.carousel:has(#img5:checked) {.carousel-imgs {transform: translateX(calc(-4*(var(--ws))))}  .img5 {background-color: var(--lbl-clr)}}
			.carousel:has(#img6:checked) {.carousel-imgs {transform: translateX(calc(-5*(var(--ws))))}  .img6 {background-color: var(--lbl-clr)}}
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
				
				<div id='news-carousel' class="carousel-wrap">
					<div class='icon chevron prev hoverable very-large inline invertable dynamic' onclick='carouselPrev()'><span class='img'></span></div>
					<div class='carousel'>
									<div class='carousel-imgs'>
									</div>
									<div class='carousel-inputs'>
									</div>
									<div class='carousel-lbls'>
									</div>
								</div>
					<div class='icon chevron next hoverable very-large inline invertable dynamic' onclick='carouselNext()'><span class='img'></span></div>
				</div>
				
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
		function carouselNext() {
									curr = document.querySelector('.carousel-inputs input:checked')
									next = curr.nextElementSibling === null ? curr.parentNode.firstElementChild : curr.nextElementSibling;
									next.checked = true;
									curr.checked = false;
									}
		
		function carouselPrev() {
									curr = document.querySelector('.carousel-inputs input:checked')
									next = curr.previousElementSibling === null ? curr.parentNode.lastElementChild : curr.previousElementSibling;
									next.checked = true;
									curr.checked = false;
									}
		
		async function carouselShifter() {
									while (true) {
										await sleep(4000)
										
										carouselNext()
									}
									}
		
		function openy(i) {
							reset(i);
							document.querySelector('.carousel-wrap').style.display = 'flex';
							}
		
		function reset(i) {
							for (j=1;j<25;j++) {
												document.querySelector(`#img${j}`).checked = i === j;
												}
							}

		// On-load logic
		window.addEventListener('load', loader);
		async function loader()  {
									// PHP sent notifications
									if (document.querySelector('body').dataset.message === 'login') {
										notify(`Utilisateur <?= $message ? $_SESSION['username']:'' ?> connecté`, 'green');
										}
									
									// Articles layout calc
									/*if (window.matchMedia(`(min-width: 1268px)`).matches) {
										n_art = 5;
									} else */
									if (window.matchMedia(`(min-width: 900px)`).matches) {
										n_art = 4;
									} else if (window.matchMedia(`(min-width: 642px)`).matches) {
										n_art = 3;
									} else if (window.matchMedia(`(min-width: 412px)`).matches) {
										n_art = 2;
									} else {
										n_art = 1;
									}
									
									// fetch most recent previews
									newslist = await fetchArticles(0, 5); // 5 most recently published articles

									inps = document.querySelector('.carousel-inputs')
									lbls = document.querySelector('.carousel-lbls')
									imgs = document.querySelector('.carousel-imgs')
									i = 1;
									newslist.data.forEach(item => {
											inp = document.createElement('input');
											i == 1 ? inp.checked = true:null;
											inp.setAttribute('type','radio');
											inp.setAttribute('id',`img${i}`);
											inps.appendChild(inp);
											
											lbl = document.createElement('label');
											lbl.setAttribute('for',`img${i}`);
											lbl.setAttribute('class',`img${i}`);
											lbl.setAttribute('onclick',`reset(${i})`);
											lbls.appendChild(lbl);
											
											imgWrap = document.createElement('div');
											imgWrap.classList.toggle('carousel-img-wrap')
											
											img = document.createElement('img');
											img.setAttribute('src',item['picture_path']);
											img.dataset.url = item['url'];
											imgWrap.appendChild(img);
											imgs.appendChild(imgWrap);
											img.addEventListener('click', e => window.location.href = '/articles/'+e.target.dataset.url)
											
											i++;
											//document.querySelector('head style').textContent += `\n.carousel:has(#img${i}:checked) {.carousel-imgs {transform: translateX(calc(-${i-1}*(100%\/var(--nb))))}  .img${i} {background-color: #fff}}`;
											})
									
									// carousel shift
									carouselShifter()
									}
		</script>
	</body>
</html>