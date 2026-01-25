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
		<title>arcENSiel - Portail de connexion</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Portail de connexion">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/login.js'></script>
		
		<style>			
			.auth-form-wrap {
							margin: auto;
							margin-bottom: 2.5em;
							margin-top: 1em;
							color: black;
							display: flex;
							gap: 1.5em;
							flex-flow:column;
							}
			
			.checker {
					position: absolute;
					top: 4.5em;
					left: 28px;
					display: none;
					flex-flow: column;
					gap: .25em;
					padding: .5em;
					border-radius: 6px;
					border: 1px solid black;
					background: white;
					z-index: 5;
					}
				
				.check-item {
							display: flex;
							flex-flow: row;
							gap: .5em;
							align-items: flex-end;
							}
			
			.box:has(input:focus) .checker {display: flex}
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
						
						<div class='form-action-wrap optional' <?= $error ? 'hidden':''?>>
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

						<section class="optional-sec" <?= $error ? 'active':''?>>
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
								<div class='checker password'>
									<div class='check-head'>Le mot de passe contient au moins :</div>
									<div class='check-item length' invalid>
										<div class='icon validity inline medium'><span class='img'></span></div>
										<div class='check-item-desc'>6 caractères</div>
									</div>
									<div class='check-item minuscule' invalid>
										<div class='icon validity inline medium'><span class='img'></span></div>
										<div class='check-item-desc'>une lettre minuscule</div>
									</div>
									<div class='check-item majuscule' invalid>
										<div class='icon validity inline medium'><span class='img'></span></div>
										<div class='check-item-desc'>une lettre majuscule</div>
									</div>
									<div class='check-item number' invalid>
										<div class='icon validity inline medium'><span class='img'></span></div>
										<div class='check-item-desc'>un chiffre</div>
									</div>
									<div class='check-item special' invalid>
										<div class='icon validity inline medium'><span class='img'></span></div>
										<div class='check-item-desc'>un caractère spécial</div>
									</div>
								</div>
							</div>

							<div class='box password-confirm'>
								<div class='input-header'>
									<label class='label' for='pswd-bis'>Confirmation du nouveau mot de passe</label>
								</div>
								<div class='input-container'>
									<div class='icon lock left medium'><span class='img'></span></div>
									<input class='lineentry decorated' type='password' id='pswd-bis' autocomplete=off placeholder='Confirmation du mot de passe' title="Veuilez confirmer votre mot de passe" required <?= $error ? '':'disabled'?>>
									<div class='icon toggle-pass bis right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
								</div>
								<div class='error confirmation'>Les mots de passe ne correspondent pas !</div>
							</div>
						</section>

						<div class='form-action-wrap optional reverse linear' <?= $error ? 'active':''?>>
							<div class='form-action'>
								<button class='action-btn update' type="submit" name="action" value="update" disabled>Enregister</button>
							</div>
							<div class='form-action'>
								<div class='action-btn cancel'>Annuler</div>
							</div>
						</div>
					</div>

					<input name='username' value="<?= $_SESSION['username'] ?>" hidden required readonly>
					<input name='csrf-token' value=<?= $_SESSION['CSRF'] ?> required hidden readonly>
				</form>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									if (document.querySelector('body').dataset.error === 'invalid-credentials') {
										document.querySelector('.error.validation').setAttribute('active','');
									}
									
									if (document.querySelector('body').dataset.error === 'locked-username') {
										notify("login non modifiable pour admin", 'red');
									}

									if (document.querySelector('body').dataset.error === 'locked-email') {
										notify("email non modifiable pour admin", 'red');
									}

									if (document.querySelector('body').dataset.message === 'updated') {
										notify(`Informations mises à jour`, 'green');
										}
									}
		
	
	// Regsiter section logic //
		// Password match check
		document.querySelector('#pswd-bis').addEventListener('input', psswdCheck)
		
		// Password validity check
		document.querySelector('#pswd').addEventListener('input', paswdRequirementsCheck)
		
		
		// (in)activate button
		let btn = document.querySelector('.action-btn.update');
		function submitAllower() {
				btn.disabled = (document.querySelectorAll('.error[active]').length !== 0)
		}
		
		// username/email check
		document.querySelector('#username').oninput = compareWithOldValue
		document.querySelector('#email').oninput = compareWithOldValue
		
		document.querySelector('input#username').onchange = e => checkInput(e, 'username')
		document.querySelector('input#email').onchange = e => checkInput(e, 'email')
		
		// password visibility manage
		document.querySelector('.toggle-pass.new').onclick = () => {togglePassword('#pswd')};
		document.querySelector('.toggle-pass.bis').onclick = () => {togglePassword('#pswd-bis')};
        document.querySelector('.toggle-pass.old').onclick = () => {togglePassword('#pswd-old')};

		// handle edition choices
		<?php if ($_SESSION['username'] !== 'admin'): ?>
		document.querySelector('.action-btn.reset-user').onclick = e => {
				document.querySelector('#username').removeAttribute('readonly');
				e.target.findParent('.form-action-wrap').toggleAttribute('hidden')
				e.target.findParent('.main-info').querySelector('.optional.reverse').toggleAttribute('active')
				}
		document.querySelector('.action-btn.reset-mail').onclick = e => {
				document.querySelector('#email').removeAttribute('readonly');
				e.target.findParent('.form-action-wrap').toggleAttribute('hidden')
				e.target.findParent('.main-info').querySelector('.optional.reverse').toggleAttribute('active')
				}
		<?php endif; ?>
		
		document.querySelector('.action-btn.reset-pswd').onclick = e => {
				document.querySelector('#pswd').disabled = false;
				document.querySelector('#pswd-old').disabled = false;
				document.querySelector('#pswd-bis').disabled = false;
				e.target.findParent('.form-action-wrap').toggleAttribute('hidden')
				e.target.findParent('.main-info').querySelector('.optional-sec').toggleAttribute('active')
				e.target.findParent('.main-info').querySelector('.optional.reverse').toggleAttribute('active')
				}
		
				document.querySelector('.action-btn.cancel').onclick = e => {
				document.querySelectorAll('.optional-sec input[type="password"]').forEach(i => {
					i.disabled = true;
					i.value = '';
					})
				document.querySelectorAll('.error').forEach(i => {
					i.removeAttribute('active')
					})
				document.querySelector('#username').value = document.querySelector('#username').dataset.id;
				document.querySelector('#username').setAttribute('readonly', '');
				e.target.findParent('.main-info').querySelector('.form-action-wrap.optional').removeAttribute('hidden')
				e.target.findParent('.main-info').querySelector('.optional-sec').removeAttribute('active')
				e.target.findParent('.main-info').querySelector('.optional.reverse').removeAttribute('active')
				}
		</script>
	</body>
</html>