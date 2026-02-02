<?php
// initialize php session - allow to keep login state throughput site navigation
// each session is associtated with a cookie containing chosen data allowing to identify user on navgation
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

// import phpCAS
require_once $root . '/libraries/vendor/apereo/phpcas/source/CAS.php';

// handle CAS login
function cas_login(): string {
	global $root;
	
	// Activer le débogage (optionnel)
	phpCAS::setDebug($root . '/app/login/cas.log');

	// Mode proxy ou client selon le besoin
	phpCAS::client(server_version: CAS_VERSION_2_0, server_hostname: 'cas.ens-lyon.fr', server_port: 443, server_uri: '/cas', service_base_url: 'https://cas.ens-lyon.fr:443/cas');
	
	// Indiquer le certificat
	phpCAS::setCasServerCACert($root . '/app/login/certificate.pem');

	// Forcer l'authentification
	phpCAS::forceAuthentication();

	// Récupérer l'identifiant utilisateur CAS
	return phpCAS::getUser();
}


// Bypass if username check asked
$check = $_GET['check'] ?? null;
if ($check === 'availability') {
	$type = $_GET['type'];
	$value = $_GET['content'] ?? null;
	
	$query = $pdo->prepare("SELECT id from users WHERE ".($type === 'email' ? 'email':'username')." = ?");
	$query->execute([$value]);
	
	if ($query->fetch()) {
		http_response_code(400);
		echo json_encode(['error' => "Valeur indisponible."]);
		exit;
	} else {
		http_response_code(200);
		echo json_encode(['value' => $user]);
		exit;
	}
}

// get token if exist
$csrf = $_POST['csrf-token'] ?? null;

// Get client-sent arguments
$method = $method ?? $_GET['auth'] ?? 'local';

// extract other variables
$action = $action ?? $_POST['action']; 	// action received from POST = from form completion -> check passed credentials
$action = $action ?? $_GET['action'];	// action received from GET = from external page call -> complete form with credentials
$action = $action ?? 'login';

$status= isset($_POST['action']) ? 'pending-check':'pending-form';


// Check log state
if (isset($_SESSION['user_id']) && $action !== 'update') {// logged in
	if ($action === 'login') {// no action
		http_response_code(200);
		$response = ['logged_in' => true, 'username' => $_SESSION['username'], 'role' => $_SESSION['role']];
	} else if ($action === 'logout') {
		session_destroy();
		http_response_code(200);
		$response = ['logged_in' => false, 'message' => 'Successfully logged out.'];
		logger('/log/users.log', "Successfully logged out.", "Logout");
	} else {
		http_response_code(400);
		$response = ['error' => "Invalid call."];
	}
	echo json_encode($response);
	header("Location: /"); 
	exit;
}


// switch btw auth methods
if ($method === 'CAS') {
	$username = cas_login();
	$password = null;
	logger('/log/users.log', "User $username: logged in with CAS successfully.", 'Login');
	$action = 'login';
} else if ($method === 'local') {// local auth
	$username = $_POST['username'] ?? null;
	$password = $_POST['password'] ?? null;
	$email = $_POST['email'] ?? null;
} else {
	http_response_code(400);
	$response = ['error' => "Invalid call."];
	echo json_encode($response);
	exit;
}

// check reset token validity upon reception
if ($action === 'reset' && $status === 'pending-form') {
	$resetToken = $_SESSION['reset-token'];

	if (!$resetToken) {
        echo "Unauthorized";
        exit;
    }
	
	$query = $pdo->prepare("SELECT expiry, user FROM tokens WHERE token = ? AND valid = TRUE");
	$query->execute([$resetToken]);
	$result = $query->fetch();

	if (!$result) {
		$_SESSION['pswd-status'] = 'bad-token';

		$query = $pdo->prepare("DELETE FROM tokens WHERE token = ?");
		// $query->execute([$resetToken]);
	
	} elseif ($result['expiry'] < time()) {
		$_SESSION['pswd-status'] = 'old-token';

		$query = $pdo->prepare("DELETE FROM tokens WHERE token = ?");
		$query->execute([$resetToken]);
	
	} else {// valid token
		$tmpuser = $result['user'];
	}
}

// Proceed with check if form has been received
if ($status === 'pending-check') {
	switch ($action) {
		case 'register':	// register account
			$query = $pdo->prepare("SELECT id FROM users WHERE username = ?");	// prepare SQL request with placeholder - check user presence
			$query->execute([$username]);		// execute request with expected values
			
			if ($query->fetch()) {// entry found : username exists
				http_response_code(400);
				$response = ['error' => "Nom d'utilisateur indisponible."];
				$_SESSION['error'] = "used-login";
				$dest = "/login?auth=local&action=register";
				break;
			}
			
			// Hash password with default php algo
			$hash = password_hash($password_token . $password, PASSWORD_DEFAULT);
			
			// Insert new user
			$sql = "INSERT INTO users (username, password_hash, auth_type) VALUES (?, ?, ?)";
			$params = [$username, $hash, $method];
			$query = $pdo->prepare($sql);
			$query->execute($params);
			
			logger('/log/users.log', queryMaker($sql, $params).PHP_EOL."Registered successfully.", 'Register');

			http_response_code(200);
			$response = ['error' => null];
			$dest = "/login?auth=local&action=login&msg=registered";
			
			echo json_encode($response);
			header("Location: $dest");
			exit;
		
		case 'login':	// log into account
			if (!isset($_SESSION['user_id'])) {
				// try and find user
				$sql = "SELECT id, password_hash, role, email FROM users WHERE username = ? AND auth_type = ?";
				$params = [$username, $method];
				$query = $pdo->prepare($sql);
				$query->execute($params);
				$user = $query->fetch();
				
				if (!$user && $method === 'CAS') {// first connection as CAS user (connected but not in db)
					// add new user to db
					$query = $pdo->prepare("INSERT INTO users (username, password_hash, auth_type) VALUES (?, ?, ?)");
					$query->execute([$username, null, $method]);
					
					// fetch updated data
					$query = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ? AND auth_type = ?");
					$query->execute([$username, $method]);
					$user = $query->fetch();
					
					http_response_code(200);
					$response = ['logged_in' => true, 'username' => $username];
					$dest = "/";
				} else if ($user && password_verify($password_token . $password, $user['password_hash'])) {// existing user  - validated credentials
					http_response_code(200);
					$response = ['logged_in' => true, 'username' => $username];
					$dest = "/";
				} else {
					$_SESSION['error'] = "invalid-credentials";
					$_SESSION['error-data'] = ['username' => $username, 'email' => $user['email']];
					
					http_response_code(400);
					$response = ['error' => "Mot de passe ou nom d'utilisateur incorrect"];
					$dest = "/login";
					break;
				}
				
				// config session
				$_SESSION['user_id'] = $user['id'];
				$_SESSION['username'] = $username;
				$_SESSION['email'] = $user['email'];
				$_SESSION['role'] = $user['role'];
				$_SESSION['CSRF'] = bin2hex(random_bytes(32)); // génère un tkn aléatoie unique pour authentifier les requêtes
				$_SESSION['message'] = "login";
				$_SESSION['method'] = $method;
				$_SESSION['previous-page'] = '/';
				$_SESSION['current-page'] = '/login/login.php';

				logger('/log/users.log', "Successfully logged in.", "Login");
				break;
				
			} else {
				http_response_code(200);
				$response = ['message' => "Utilisateur déjà connecté", 'username' => $username];
				$dest = "/";
				break;
			}
		
		case 'update':	// register account
			if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
				http_response_code(401);
				$reponse = ["error" => "Unauthorized"];
				$dest = "/";
				exit;
			}
			
			// extract saved data for user
			$query = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? AND auth_type = ?");
			$query->execute([$username, 'local']);
			$user = $query->fetch();
			
			// extract new values
			$new_username = $_POST['new-username'] ?? null;
			$new_password = $_POST['new-password'] ?? null;
			$new_email = $_POST['new-email'] ?? null;

			if ($username !== $new_username) {// username update
					if ($username === 'admin') {
							$_SESSION['error'] = "locked-username";
							$_SESSION['error-data'] = 'empty';
							
							http_response_code(400);
							$response = ['error' => "Nom d'utilisateurice non modifiable"];
							$dest = "/user/myaccount";
							break;
					}
				
					$query = $pdo->prepare("UPDATE users SET username = :username WHERE id = :userid;");
					$query->execute([':username' => $new_username, ':userid' => $_SESSION['user_id']]);

					$_SESSION['username'] = $new_username;

					$response = ['message' => "Nom d'utilisateurice mis à jour pour $username"];

					logger('/log/users.log', "User $username renamed to $new_username.", "Credentials update");
					$username = $new_username;
			
			} if ($email !== $new_email) {// username update
					if ($username === 'admin') {
							$_SESSION['error'] = "locked-email";
							$_SESSION['error-data'] = 'empty';
							
							http_response_code(400);
							$response = ['error' => "Adresse email non modifiable"];
							$dest = "/user/myaccount";
							break;
					}
				
					$query = $pdo->prepare("UPDATE users SET email = :email WHERE id = :userid;");
					$query->execute([':email' => $new_email, ':userid' => $_SESSION['user_id']]);

					$_SESSION['email'] = $new_email;
					$response = ['message' => "Adresse mail mise à jour pour $username"];

					logger('/log/users.log', "Mail changed from $email to $new_email for $username", "Credentials update");
					$mail = $new_email;
			
			} if ($password !== $new_password) {
					if (password_verify($password_token . $password, $user['password_hash'])) {// existing user  - validated credentials
						// Hash new password with default php algo
						$hash = password_hash($password_token . $new_password, PASSWORD_DEFAULT);

						// Update password hash
						$sql = "UPDATE users SET password_hash = :hash WHERE id = :userid;";
						$params = [':hash' => $hash, ':userid' => $_SESSION['user_id']];
						$query = $pdo->prepare($sql);
						$query->execute($params);

						logger('/log/users.log', "Password changed.", "Credentials update");

						$response = ['message' => "Mot de passe mis à jour pour $username"];
					} else {
							$_SESSION['error'] = "invalid-credentials";
							$_SESSION['error-data'] = 'empty';
							
							http_response_code(400);
							$response = ['error' => "Mot de passe ou nom d'utilisateur incorrect"];
							$dest = "/user/myaccount";
							break;
					}
			}
			$_SESSION['message'] = "updated";
			http_response_code(200);
			$dest = "/user/myaccount";
			break;
		
		case 'reset':	// send password reset
			// Hash password with default php algo
			$hash = password_hash($password_token . $password, PASSWORD_DEFAULT);
			
			// Insert new user
			$sql = "UPDATE users SET password_hash = ? WHERE username = ?";
			$params = [$hash, $username];
			$query = $pdo->prepare($sql);
			$query->execute($params);
			
			logger('/log/users.log', queryMaker($sql, $params).PHP_EOL."New password for $username.", 'Reset password');
			
			// delete reset token
			$query = $pdo->prepare("DELETE FROM tokens WHERE username = ?");
			$query->execute([$username]);

			$dest = "/login";

			echo json_encode($response);
			header("Location: $dest");
			exit;

		case 'trigger-reset':	// send password reset
			$query = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
			$query->execute([$email]);
			$result = $query->fetch();
			
			if ($result) {
				// generate token
				$token = bin2hex(random_bytes(64));

				// save token
				$sql = "INSERT INTO tokens (user, token, expiry, valid) VALUES (:user, :token, :time, TRUE);";
				$query = $pdo->prepare($sql);
				$query->bindValue(':user', $result['username']);
				$query->bindValue(':token', $token);
				$query->bindValue(':time', time() + 3600, PDO::PARAM_INT);
				$query->execute();

				$subject = "Réinitialisez votre mot de passe";
				$body = "<h3>Bonjour $email,</h3>
				<div>Nous avons reçu une demande de réinitialisation de mot de passe. Pour définir votre nouveau mot de passe, cliquez sur le lien ci-dessous :</div>
				<div><a href='localhost:8000/login/reset-password?&token=$token'>Réinitialiser le mot de passe</a></div>

				<div style='margin-top:1em;'><small><i>Le lien est valide une heure. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.</i></small></div>";
				
				// send mail
				$delivery = sendMail(0, 'arcensiel.noreply@ens-lyon.fr', $email, 'Noreply arcENSiel', $subject, $body, 'Associatif/arcENSiel/Website Requests');
			}

			$dest = "/login/forgotten-password";
			$_SESSION['tmpmail'] = $email;
			$_SESSION['pswd-status'] = 'triggered';

			echo json_encode($response);
			header("Location: $dest");
			exit;
		
		default:
			$connected = false;
		}
	
	// send response
	echo json_encode($response);
	header("Location: $dest");
	exit;
}

//
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

$errordata = $_SESSION['error-data'] ?? null;
unset($_SESSION['error-data']);

$resetstatus = $_SESSION['pswd-status'] ?? null;
unset($_SESSION['pswd-status']);

$tmpmail = $_SESSION['tmpmail'] ?? null;
unset($_SESSION['tmpmail']);

$message = $_GET['msg'] ?? null;
unset($_SESSION['message']);
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
							width: 40em;
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

			a:not(:hover) {color: unset}
		</style>
	</head>
	
	<body
		<?php if ($error): ?>
			data-error="<?= htmlspecialchars($error, ENT_QUOTES) ?>";
		<?php endif; ?>
		<?php if ($message): ?>
			data-message="<?= htmlspecialchars($message, ENT_QUOTES) ?>";
		<?php endif; ?>
		>
		
		<div id='banner'>
			<a class='banner-logo-wrap' href="/">
				<div class="banner-logo">
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_a.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_r.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_c.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_e1.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_n.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_s.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_i.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_e2.png'>
					</span>
					<span class='banner-letter' mode='wave'>
						<img src='/files/icons/logo_l.png'>
					</span>
				</div>
			</a>
	    </div>
		
		<div id="main">
			<div class="auth-form-wrap">
				<form id='auth-form' class='wrap' method='post' action='/login/submit?auth=local'>
					<h1 class="title">
						<?php if ($action === 'login'): ?>
							Se connecter
						<?php endif; ?>
						<?php if ($action === 'register'): ?>
							S'inscrire
						<?php endif; ?>
						<?php if ($action === 'trigger-reset' || $action === 'reset'): ?>
							Réinitialiser son mot de passe
						<?php endif; ?>
					</h1>
					
					<div class='main-info'>
						
						<div>
							<?php if ($resetstatus === 'triggered'): ?>
								Si un compte est rattaché au mail <i><?= $tmpmail ?></i>, un email contenant un lien de réinitialisation a été envoyé.
							<?php endif; ?>
							<?php if ($resetstatus === 'bad-token'): ?>
								Lien invalide.
							<?php endif; ?>
							<?php if ($resetstatus === 'old-token'): ?>
								Lien expiré. Efectuez une nouvelle demande de réinitialisation.
							<?php endif; ?>
						</div>
						
						<?php if ($action !== 'trigger-reset' && !$resetstatus): ?>
						<div class='box username'>
							<div class='input-header'>
								<label class='label' for='username'>Nom d'utilisateur</label>
							</div>
							<div class='input-container'>
								<div class='icon username left medium <?= $action === 'register' ? 'right':'' ?>'><span class='img'></span></div>
								<input class='lineentry decorated' type='text' name='username' id='username' autocomplete=on required placeholder="Nom d'utilisateur" title="Veuillez entrer votre nom d'utilisateur"
									<?php if ($error === 'invalid-credentials'): ?>
										value=<?= $errordata['username'] ?>
									<?php endif; ?>
									<?php if ($action === 'reset'): ?>
										value=<?= $tmpuser ?>
										readonly
									<?php endif; ?>
								>
								<div class='icon validity right medium'><span class='img'></span></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error username'>Nom d'utilisateur indisponible.</div>
						</div>
						<?php endif; ?>
						
						<?php if ($action === 'register' || ($action === 'trigger-reset' && !$resetstatus)): ?>
						<div class='box email'>
							<div class='input-header'>
								<label class='label' for='email'>Adresse email</label>
							</div>
							<div class='input-container'>
								<div class='icon mail left medium'><span class='img'></span></div>
								<input class='lineentry decorated' type='email' name='email' id='email' autocomplete=on required placeholder='john.doe@example.com' title="Veuilez entrer votre adresse mail"
									<?php if ($error === 'used-mail'): ?>
										value=<?= $errordata['email'] ?>
									<?php endif; ?>
								>
								<div class='icon validity right medium'><span class='img'></span></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error mail'>Cette adresse mail existe déjà.</div>
						</div>
						<?php endif; ?>
						
						<?php if ($action !== 'trigger-reset' && !$resetstatus): ?>
						<div class='box password'>
							<div class='input-header'>
								<label class='label' for='pswd'>Mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left medium'><span class='img'></span></div>
								<input class='lineentry decorated livecheck' type='password' name='password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe" pattern='(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^\sa-zA-Z0-9]).{6,}'>
								<div class='icon toggle-pass right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
							</div>
							<div class='error password' <?= $error === 'invalid-credentials' ? 'active':'' ?>><?= $action === 'login' ? "Nom d'utilisateur ou mot de passe incorrect":"Le mot de passe n'est pas valide" ?></div>
							<?php if ($action === 'login'): ?><div class='context right mail'><a class="canonic" href='/login/forgotten-password'>Mot de passe oublié ?</a></div><?php endif; ?>

							<?php if ($action === 'register'): ?>
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
							<?php endif; ?>
						</div>
						<?php endif; ?>
						
						<?php if ($action === 'register' || ($action === 'reset' && !$resetstatus)): ?>
						<div class='box password-confirm'>
							<div class='input-header'>
								<label class='label' for='pswd-bis'>Confirmation du mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left medium'><span class='img'></span></div>
								<input class='lineentry decorated' type='password' id='pswd-bis' autocomplete=off required placeholder='Confirmation du mot de passe' title="Veuilez confirmer votre mot de passe">
								<div class='icon toggle-pass bis right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
							</div>
							<div class='error confirmation'>Les mots de passe ne correspondent pas !</div>
						</div>
						<?php endif; ?>
					</div>
					
					
					<div class='form-action-wrap'>
						<?php if ($action === 'login'): ?>
							<div class='form-action'>
								<button class='action-btn' type='submit' name='action' value='login'>Se connecter</button>
							</div>
						<?php endif; ?>
						<?php if ($action === 'register'): ?>
							<div class='form-action'>
								<button class='action-btn register' name='action' value='register'>S'inscrire</button>
							</div>
						<?php endif; ?>
						<?php if ($action === 'trigger-reset' && !$resetstatus): ?>
							<div class='form-action'>
								<button class='action-btn' name='action' value='trigger-reset'>Continuer</button>
							</div>
						<?php endif; ?>
						<?php if ($action === 'reset' && !$resetstatus): ?>
							<div class='form-action'>
								<button class='action-btn' name='action' value='reset'>Réinitialiser</button>
							</div>
						<?php endif; ?>
					</div>
					
				</form>
				
				<?php if ($action === 'register' || $action === 'login'): ?>
				<div class='external form-action-wrap'>
					<?php if ($action === 'login'): ?>
						<div class='form-action'>
							<span class='label'>Pas encore de compte ?</span>
							<button class='action-btn'><a href='/register'>S'inscrire</a></button>
						</div>
					<?php endif; ?>
					<?php if ($action === 'register'): ?>
						<div class='form-action'>
							<span class='label'>Déjà un compte ?</span>
							<button class='action-btn'><a href='/login'>Se connecter</a></button>
						</div>
					<?php endif; ?>
					<?php if (false): ?>
						<div class='form-action'>
							<button class='action-btn'>
								<a href='/loginCAS'>Se connecter avec ENS de Lyon</a>
							</button>
						</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		// On-load logic
		window.addEventListener('load', loader)
		async function loader()  {
									if (document.querySelector('body').dataset.error === 'used-login') {
										document.querySelector('.error.username').style.display='';
									}
									if (document.querySelector('body').dataset.error === 'invalid-credentials') {
										document.querySelector('.error.password').style.display='';
									}
									if (document.querySelector('body').dataset.message === 'registered') {
										notify('Compte créé');
									}
									
									}
		
		
	<?php if ($action === 'register' || $action === 'reset'): ?>
	// Regsiter section logic //
		// Password match check
		document.querySelector('#pswd-bis').addEventListener('input', psswdCheck)

		// Password validity check
		document.querySelector('#pswd').addEventListener('input', paswdRequirementsCheck)
		
		// (in)activate button
		function submitAllower() {
				btn = document.querySelector('.action-btn.register');
				
				btn.disabled = (document.querySelector('.error[active]') !== null || document.querySelector('.check-item[invalid]') !== null)
		}
		
		// username/email check
		document.querySelector('#username').oninput = compareWithOldValue
		try {document.querySelector('#email').oninput = compareWithOldValue} catch(e) {null}
		
		
		document.querySelector('input#username').onchange = e => checkInput(e, 'username')
		document.querySelector('input#email').onchange = e => checkInput(e, 'email')
		
	<?php endif; ?>
		</script>
	</body>
</html>