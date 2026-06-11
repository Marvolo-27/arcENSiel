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
				<a class='icon go-back inline large hoverable dynamic' href="/user/dashboard"></a>
				<h1>Gérer ses identifiants</h1>
			</div>
			
			<div class="auth-form-wrap">
				<form id='auth-form' class='wrap' method='post' action='/login/update-credentials'>
					<div class='main-info'>
						<div class='box username'>
							<div class='input-header'>
								<div class='icon info inline hoverable'>
									<span class='infotext'>
										Le nom d'utilisateurice doit comporter au moins 3 caractères, et ne pas débuter par '-'. Caractères autorisés : a-z, A-Z, 0-9, '_', '-'
									</span>
								</div>

								<label class='label' for='username'>Nom d'utilisateur</label>
							</div>
							<div class='input-container'>
								<div class='icon username left'></div>
								<input class='lineentry decorated' type='text' name='new-username' id='username' autocomplete=on pattern="[a-zA-Z_]+[a-zA-Z0-9_-]{2,}" placeholder="Nom d'utilisateur" initial-value="<?= $_SESSION['username'] ?>" value="<?= $_SESSION['username'] ?>" title="Veuillez entrer votre nom d'utilisateur" required readonly>
								<div class='icon validity right'></div>
								<span class="icon spinner right"></span>
							</div>
							<div class='error username'>Nom d'utilisateur indisponible.</div>
						</div>

						<div class='box email'>
							<div class='input-header'>
								<label class='label' for='email'>Adresse email</label>
							</div>
							<div class='input-container'>
								<div class='icon mail left'></div>
								<input class='lineentry decorated' type='email' name='new-email' id='email' autocomplete=on pattern="[a-z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" placeholder='john.doe@example.com' initial-value="<?= $_SESSION['email'] ?>" value="<?= $_SESSION['email'] ?>" title="Veuilez entrer votre adresse mail" required readonly>
								<div class='icon validity right'></div>
								<span class="icon spinner right"></span>
							</div>
							<div class='error mail'>Cette adresse mail existe déjà.</div>
						</div>
						
						<div class='actions-wrap reset-options linear responsive' <?= $error ? '':'active'?>>
							<?php if ($_SESSION['username'] !== 'admin'): ?>
							<div class='action-container'>
								<div class='action-btn reset' data-type="username">Modifier le nom d'utilisateur</div>
							</div>
							<div class='action-container'>
								<div class='action-btn reset' data-type="email">Modifier l'adresse mail</div>
							</div>
							<?php endif; ?>
							<div class='action-container'>
								<div class='action-btn reset-pswd'>Modifier le mot de passe</div>
							</div>
						</div>

						<div class="reset-section activable" <?= $error ? 'active':''?>>
							<div class='box old-password'>
								<div class='input-header'>
									<label class='label' for='pswd-old'>Mot de passe actuel</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left'></div>
									<input class='lineentry decorated' type='password' name='password' id='pswd-old' autocomplete=off placeholder='Mot de passe actuel' title="Veuilez entrer votre mot de passe actuel" required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass old right hoverable' title='Montrer le mot de passe'></div>
								</div>
								<div class='error validation'>Mot de passe incorrect.</div>
							</div>

							<div class='box password'>
								<div class='input-header'>
									<label class='label' for='pswd'>Nouveau mot de passe</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left'></div>
									<input class='lineentry decorated livecheck' type='password' name='new-password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe" pattern='(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^\sa-zA-Z0-9]).{6,}' required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass new right hoverable' title='Montrer le mot de passe'></div>
								</div>
								<div class='error password'>Le mot de passe n'est pas valide</div>
								<?php include($root . '/app/templates/password-requirements.html') ?>
							</div>

							<div class='box password-confirm'>
								<div class='input-header'>
									<label class='label' for='pswd-confirm'>Confirmation du nouveau mot de passe</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left'></div>
									<input class='lineentry decorated' type='password' id='pswd-confirm' autocomplete=off placeholder='Confirmation du mot de passe' title="Veuilez confirmer votre mot de passe" required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass bis right hoverable' title='Montrer le mot de passe'></div>
								</div>
								<div class='error confirmation'>Les mots de passe ne correspondent pas !</div>
							</div>
							</div>

						<div class='actions-wrap reset-actions linear responsive' <?= $error ? 'active':''?>>
							<div class='action-container'>
								<button class='action-btn update' type="submit" name="action" value="update-credentials" disabled>Enregister</button>
							</div>
							<div class='action-container'>
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
										Error('validation').show()
									}
									
									if (body.dataset.error === 'locked-username') {
										notify("Opération interdite", "Le login n'est pas modifiable pour le compte admin", 'red')
									}

									if (body.dataset.error === 'locked-email') {
										notify("Opération interdite", "L'adresse email n'est pas modifiable pour le compte admin", 'red')
									}

									if (body.dataset.message === 'updated') {
										notify("Succès", "Informations de connexion mises à jour.", 'green')
										}
									}
		
	
	// Regsiter section logic //
		// (in)activate button
		function submitAllower(force = false) {Button('update').disabled = force || (Errors('[active]').length !== 0)}
	
		// Password checks
		Input('pswd-confirm').oninput = pswdCheck
		Input('pswd').oninput = pswdRequirementsCheck
		
		// username/email check
		Input('username').oninput = compareWithOldValue
		Input('email').oninput = compareWithOldValue
		
		Input('username').onchange = e => checkInput(e.target, '/auth/login', 'username')
		Input('email').onchange = e => checkInput(e.target, '/auth/login', 'email')

		// handle edition choices
		<?php if ($_SESSION['username'] !== 'admin'): ?>
		Buttons('reset').forEach(button => button.onclick = e => {
			Input(e.target.dataset.type).readOnly = false
			Section('reset-actions').show()
			Section('reset-options').hide()
		})
		<?php endif; ?>
		
		Button('reset-pswd').onclick = () => {
				// reset inputs
				Input('pswd').disabled = false
				Input('pswd-old').disabled = false
				Input('pswd-confirm').disabled = false
				
				// update DOM status
				Section('reset-section').show()
				Section('reset-actions').show()
				Section('reset-options').hide()
				}
		
		Button('cancel').onclick = () => {
				// reset inputs
				Input('username').value = Input('username').getAttribute('initial-value')
				Input('username').readOnly = true;
				Input('email').value = Input('email').getAttribute('initial-value')
				Input('email').readOnly = true;

				// reset DOM elements
				Section('reset-section').inputs('[type="password"]').forEach(i => {
					i.disabled = true;
					i.value = '';
					})
				Errors().forEach(i => i.hide())
				
				// update DOM status
				Section('reset-options').show()
				Section('reset-section').hide()
				Section('reset-actions').hide()
				}
		</script>
	</body>
</html>