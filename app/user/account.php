<?php
// initialize php session - allow to keep login state throughput site navigation
// each session is associtated with a cookie containing chosen data allowing to identify user on navgation
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

if (!isset($_SESSION['user_id'])) {
	echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	header("Location: /");
	exit;
}

if (isset($_SESSION['error'])) {
	$error = $_SESSION['error'];
	unset($_SESSION['error']); // remove for future page load
} else {
	$error = false;
}

if (isset($_SESSION['message'])) {
	$message = $_SESSION['message'];
	unset($_SESSION['message']); // remove for future page load
} else {
	$message = false;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Paramètres de compte</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Paramètres de compte">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/credentials.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/login.js'></script>

		<style>
			:is(.reset-section,.reset-options):not([active]){
					display: none;
			}
		</style>
	</head>
	
	<body   <?php if ($message): ?>
				data-message="<?= htmlspecialchars($message, ENT_QUOTES) ?>"
			<?php endif; ?>

			 <?php if ($error): ?>
				data-error="<?= htmlspecialchars($error, ENT_QUOTES) ?>"
			<?php endif; ?>
			>
		
		<?php include($root . '/app/templates/header.php') ?>
		
		<div id="main">
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/user/dashboard"><div class='img'></div></a>
				<h1>Gérer ses identifiants</h1>
			</div>
			
			<div class="auth-form-wrap">
				<form id='auth-form' class='wrap' method='post' action='/login/update-credentials'>
					<div class='main-info'>
						<div class='box username'>
							<div class='input-header'>
								<label class='label' for='username'>Nom d'utilisateur</label>
							</div>
							<div class='input-container'>
								<div class='icon username left medium <?= $action === 'register' ? 'right':'' ?>'><span class='img'></span></div>
								<input class='lineentry decorated' type='text' name='new-username' id='username' autocomplete=on placeholder="Nom d'utilisateur" data-id="<?= $_SESSION['username'] ?>" value="<?= $_SESSION['username'] ?>" title="Veuillez entrer votre nom d'utilisateur" required readonly>
								<div class='icon validity right medium'><span class='img'></span></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error username'>Nom d'utilisateur indisponible.</div>
						</div>

						<div class='box email'>
							<div class='input-header'>
								<label class='label' for='email'>Adresse email</label>
							</div>
							<div class='input-container'>
								<div class='icon mail left medium'><span class='img'></span></div>
								<input class='lineentry decorated' type='email' name='new-email' id='email' autocomplete=on placeholder='john.doe@example.com' data-id="<?= $_SESSION['email'] ?>" value="<?= $_SESSION['email'] ?>" title="Veuilez entrer votre adresse mail" required readonly>
								<div class='icon validity right medium'><span class='img'></span></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error mail'>Cette adresse mail existe déjà.</div>
						</div>
						
						<div class='form-action-wrap reset-options linear' <?= $error ? '':'active'?>>
							<?php if ($_SESSION['username'] !== 'admin'): ?>
							<div class='form-action'>
								<div class='action-btn reset-user'>Modifier le nom d'utilisateur</div>
							</div>
							<div class='form-action'>
								<div class='action-btn reset-mail'>Modifier l'adresse mail'</div>
							</div>
							<?php endif; ?>
							<div class='form-action'>
								<div class='action-btn reset-pswd'>Modifier le mot de passe</div>
							</div>
						</div>

						<section class="reset-section" <?= $error ? 'active':''?>>
							<div class='box old-password'>
								<div class='input-header'>
									<label class='label' for='pswd-old'>Mot de passe actuel</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left medium'><span class='img'></span></div>
									<input class='lineentry decorated' type='password' name='password' id='pswd-old' autocomplete=off placeholder='Mot de passe actuel' title="Veuilez entrer votre mot de passe actuel" required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass old right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
								</div>
								<div class='error validation'>Mot de passe incorrect.</div>
							</div>

							<div class='box password'>
								<div class='input-header'>
									<label class='label' for='pswd'>Nouveau mot de passe</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left medium'><span class='img'></span></div>
									<input class='lineentry decorated livecheck' type='password' name='new-password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe" pattern='(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^\sa-zA-Z0-9]).{6,}' required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass new right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
								</div>
								<div class='error password'>Le mot de passe n'est pas valide</div>
								<?php include($root . '/app/templates/password-requirements.html') ?>
							</div>

							<div class='box password-confirm'>
								<div class='input-header'>
									<label class='label' for='pswd-confirm'>Confirmation du nouveau mot de passe</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left medium'><span class='img'></span></div>
									<input class='lineentry decorated' type='password' id='pswd-confirm' autocomplete=off placeholder='Confirmation du mot de passe' title="Veuilez confirmer votre mot de passe" required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass bis right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
								</div>
								<div class='error confirmation'>Les mots de passe ne correspondent pas !</div>
							</div>
						</section>

						<div class='form-action-wrap reset-actions responsive linear' <?= $error ? 'active':''?>>
							<div class='form-action'>
								<button class='action-btn update' type="submit" name="action" value="update-credentials" disabled>Enregister</button>
							</div>
							<div class='form-action'>
								<div class='action-btn cancel'>Annuler</div>
							</div>
						</div>
					</div>

					<input name='username' value="<?= $_SESSION['username'] ?>" hidden required readonly>
					<input id='csrf' name='csrf-token' value=<?= $_SESSION['CSRF'] ?> data-csrf=<?= $_SESSION['CSRF'] ?> required hidden readonly>
				</form>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									if (body.dataset.error === 'invalid-credentials') {
										Error('validation').show();
									}
									
									if (body.dataset.error === 'locked-username') {
										notify("login non modifiable pour admin", 'red');
									}

									if (body.dataset.error === 'locked-email') {
										notify("email non modifiable pour admin", 'red');
									}

									if (body.dataset.message === 'updated') {
										notify(`Informations mises à jour`, 'green');
										}
									}
		
	
	// Regsiter section logic //
		// Password match check
		Input('pswd-confirm').addEventListener('input', psswdCheck)
		
		// Password validity check
		Input('pswd').addEventListener('input', paswdRequirementsCheck)
		
		
		// (in)activate button
		function submitAllower() {
				Button('update').disabled = (document.querySelectorAll('.error[active]').length !== 0)
		}
		
		// username/email check
		Input('username').oninput = compareWithOldValue
		Input('email').oninput = compareWithOldValue
		
		Input('username').onchange = e => checkInput(e, 'username')
		Input('email').onchange = e => checkInput(e, 'email')

		// handle edition choices
		<?php if ($_SESSION['username'] !== 'admin'): ?>
		Button('reset-user').onclick = e => {
				Input('username').readOnly = false;
				e.target.findParent('.main-info').querySelector('.reset-actions').show()
				e.target.findParent('.main-info').querySelector('.reset-options').hide()
				}
		Button('reset-mail').onclick = e => {
				Input('email').readOnly = false;
				e.target.findParent('.main-info').querySelector('.reset-actions').show()
				e.target.findParent('.main-info').querySelector('.reset-options').hide()
				}
		<?php endif; ?>
		
		Button('reset-pswd').onclick = e => {
				// DOM elements
				coreInfo = e.target.findParent('.main-info');
				
				// reset inputs
				Input('pswd').disabled = false;
				Input('pswd-old').disabled = false;
				Input('pswd-confirm').disabled = false;
				
				// update DOM status
				e.target.findParent('.form-action-wrap').hide()
				coreInfo.child('.reset-section').show()
				coreInfo.child('.reset-actions').show()
				coreInfo.child('.reset-options').hide()
				}
		
		Button('cancel').onclick = e => {
				// DOM elements
				coreInfo = e.target.findParent('.main-info');

				// reset inputs
				Input('username').value = Input('username').dataset.id;
				Input('username').readOnly = true;
				Input('email').value = Input('email').dataset.id;
				Input('email').readOnly = true;

				// reset DOM elements
				document.querySelector('.reset-section').childs('input[type="password"]').forEach(i => {
					i.disabled = true;
					i.value = '';
					})
				document.querySelectorAll('.error').forEach(i => {
					i.hide()
					})
				
				// update DOM status
				coreInfo.child('.reset-options').show()
				coreInfo.child('.reset-section').hide()
				coreInfo.child('.reset-actions').hide()
				}
		</script>
	</body>
</html>