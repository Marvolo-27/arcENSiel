<?php
session_start();

$error = $_SESSION['contact-error'] ?? false;
unset($_SESSION['contact-error']);

if ($error) {
	$data = $_SESSION['contact-data'];
	unset($_SESSION['contact-data']);
}

$message = $_SESSION['contact-message'] ?? false;
unset($_SESSION['contact-message']);

?>

<!DOCTYPE html>
<html>
	<head>
		<title>Nous contacter</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
	</head>	
	
	<body
		<?php if ($error): ?>
			data-error="<?= htmlspecialchars($error, ENT_QUOTES) ?>"
		<?php endif; ?>

		<?php if ($message): ?>
			data-message="<?= htmlspecialchars($message, ENT_QUOTES) ?>"
		<?php endif; ?>
		>
	    
		<?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
            <h1>
                Formulaire de contact
            </h1>

			<div class='contact-form-wrap'>
				<form id='contact-form' method='post' action='/engine/contact'>
					<div class='required-msg'>champs requis</div>

					<div class='main-info'>
						<div class='box name'>
							<div class='input-header'>
								<label class='label' for='name'>Nom & Prénom</label>
							</div>
							<div class='input-container'>
								<input class='lineentry' type='text' name='name' id='name' autocomplete=off value="<?= $error ? $data['name']:'' ?>" required placeholder='Nom'>
							</div>
						</div>
						
						<div class='box mail'>
							<div class='input-header'>
								<label class='label' for='mail'>Email</label>
							</div>
							<div class='input-container'>
								<input class='lineentry' type='email' name='mail' id='mail' autocomplete=on value="<?= $error ? $data['mail']:'' ?>" required placeholder='john.doe@example.com'>
							</div>
							<div class='error mail'>Adresse mail invalide.</div>
						</div>
						
						<div class='box topic'>
							<div class='input-header'>
								<label class='label' for='topic'>Sujet</label>
							</div>
							<div class='input-container'>
								<input class='lineentry' type='text' name='topic' id='topic' autocomplete=off value="<?= $error ? $data['topic']:'' ?>" required placeholder='Sujet'>
							</div>
						</div>

						<div class='box comments'>
							<div class='input-header'>
								<label class='label' for='content'>Contenu</label>
							</div>
							<div class='input-container'>
								<textarea class='textentry' rows=6 cols=30 name='content' id='content' autocomplete=off placeholder='' required><?= $error ? $data['content']:'' ?></textarea>
							</div>
						</div>
					</div>
					
					<div class="form-action-wrap linear">
						<div class='form-action'>
							<button class='action-btn save'>Envoyer</button>
						</div>
						<div class='form-action'>
							<button class='action-btn reset' type='reset'>Réinitialiser</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<?php include($root . '/app/templates/footer.html') ?>

		<script>
			// On-load logic
			window.addEventListener('load', loader)
			async function loader()  {
										if (document.querySelector('body').dataset.error === 'bad-mail') {
											document.querySelector('.error.mail').setAttribute('active', '');
										} else if (document.querySelector('body').dataset.error === 'send-failed') {
											notify("Échec de l'envoi du mail", 'red')
										} else if (document.querySelector('body').dataset.message === 'sent') {
											notify("Message envoyé !", 'green')
										} else if (document.querySelector('body').dataset.test === 'test') {
											notify("OK", 'orange')
										}
										
										}
		</script>
	</body>
</html>