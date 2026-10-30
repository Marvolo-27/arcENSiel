<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

// import phpCAS
require_once $root . '/libraries/vendor/apereo/phpcas/source/CAS.php';

// handle CAS login
function cas_login(): string {
	global $root;
	
	// Activer le débogage (optionnel)
	phpCAS::setDebug($root . '/app/auth/cas.log');

	// Mode proxy ou client selon le besoin
	phpCAS::client(server_version: CAS_VERSION_2_0, server_hostname: 'cas.ens-lyon.fr', server_port: 443, server_uri: '/cas', service_base_url: 'https://cas.ens-lyon.fr:443/cas');
	
	// Indiquer le certificat
	phpCAS::setCasServerCACert($root . '/app/auth/certificate.pem');

	// Forcer l'authentification
	phpCAS::forceAuthentication();

	// Récupérer l'identifiant utilisateur CAS
	return phpCAS::getUser();
}

// get token if exist
$csrf = $_POST['csrf-token'] ?? $_GET['token'] ?? null;

// Get client-sent arguments
$method ??= $_GET['method'] ?? 'local';

// extract other variables
$action ??= $_POST['action'] ?? $_GET['action'] ?? 'login';
	// action received from POST = from form completion -> check passed credentials
	// action received from GET = from external page call -> complete form with credentials

// Form check
$status= isset($_POST['action']) ? 'pending-check':'pending-form';

// Bypass if username check asked
if ($action === 'check') {
	$type = $_GET['type'];
	$value = $_GET['content'] ?? null;
	
	$query = $pdo->prepare("SELECT id from users WHERE ".($type === 'email' ? 'email':'username')." = ?");
	$query->execute([$value]);
	
	if ($query->fetch()) {
		http_response_code(400);
		echo json_encode(['error' => "Valeur indisponible."]);
	} else {
		http_response_code(200);
		echo json_encode(['message' => 'Valeur disponible.']);
	}
	exit;
}

//// Check log state
if (isset($_SESSION['user_id'])) {// logged in
	if ($action === 'login') {// no action - send back user info
		http_response_code(200);
		$response = ['username' => $_SESSION['username'], 'role' => $_SESSION['role']];
	
	} else if ($action === 'logout') {
		$_SESSION = [];
		$_SESSION['message'] = 'logged-out';

		http_response_code(200);
		logger('/log/users.log', "Successfully logged out.", "Logout");
	
	} elseif ($action === 'update-credentials') {
		null;

	} else {
		http_response_code(400);
		$response = ['error' => "Invalid call."];
	}

	header("Location: /");
	echo json_encode($response ?? ['message' => null]);
	exit;
}


//// switch btw auth methods
if ($method === 'CAS') {
	$username = cas_login();
	$password = null;
	logger('/log/users.log', "User $username: logged in with CAS successfully.", 'Login');
	$action = 'login';
} else if ($method === 'local') {
	$username = $_POST['username'] ?? null;
	$password = $_POST['password'] ?? null;
	$email = $_POST['email'] ?? null;
} else {
	http_response_code(400);
	$response = ['error' => "Invalid call."];
	echo json_encode($response);
	exit;
}

//// check reset token validity upon reception
if ($action === 'reset-credentials' && $status === 'pending-form') {
	$resetToken = $_SESSION['reset-token'] ?? null;
	$_SESSION['tmp-data'] = [];

	if ($resetToken) {
		$query = $pdo->prepare("SELECT expiry, user FROM tokens WHERE token = ? AND valid = TRUE");
		$query->execute([$resetToken]);
		$result = $query->fetch();

		if (!$result) {
			$resetstatus = 'bad-token';

			// $query = $pdo->prepare("DELETE FROM tokens WHERE token = ?");
			// $query->execute([$resetToken]);
		
		} elseif ($result['expiry'] < time()) {
			$resetstatus = 'old-token';

			$query = $pdo->prepare("DELETE FROM tokens WHERE token = ?");
			$query->execute([$resetToken]);
		
		} else {// valid token
			$_SESSION['tmp-data']['user'] = $result['user'];
		}
	}
}

// Proceed with check if form has been received
if ($status === 'pending-check') {
	$dest = null;

	switch ($action) {
		case 'register':	// register account
			// Username unicity
			$query = $pdo->prepare("SELECT id FROM users WHERE username = ?");	// prepare SQL request with placeholder - check user presence
			$query->execute([$username]);		// execute request with expected values
			
			if ($query->fetch()) {// entry found : username exists
				http_response_code(400);
				$response = ['error' => "Nom d'utilisateur indisponible."];
				$_SESSION['error'] = "used-login";
				$dest = "/auth/register?method=local";
				break;
			}

			// Email unicity
			$query = $pdo->prepare("SELECT id FROM users WHERE mail = ?");	// prepare SQL request with placeholder - check user presence
			$query->execute([$email]);		// execute request with expected values
			
			if ($query->fetch()) {// entry found : email exists
				http_response_code(400);
				$response = ['error' => "Email indisponible."];
				$_SESSION['error'] = "used-mail";
				$dest = "/auth/register?method=local";
				break;
			}
			
			// Pattern match
			if (!preg_match('/[a-zA-Z_]+[a-zA-Z0-9_-]{2,}/', $username)) {
				http_response_code(400);
				$response = ['error' => "Nom d'utilisateur invalide."];
				$_SESSION['error'] = "bad-pattern";
				$dest = "/auth/register?method=local";
				break;
			}

			// Hash password with default php algo
			$hash = password_hash($password_token . $password, PASSWORD_DEFAULT);
			
			try {
				// Insert new user
				$sql = "INSERT INTO users (username, password_hash, auth_type) VALUES (?, ?, ?)";
				$params = [$username, $hash, $method];
				$query = $pdo->prepare($sql);
				$query->execute($params);
				
				logger('/log/users.log', queryMaker($sql, $params).PHP_EOL."Registered successfully.", 'Register');

				http_response_code(200);
				$response = ['error' => null];
				$dest = "/auth/login?method=local&msg=registered";
			} catch (PDOException $e) {
				logger('/log/users.log', queryMaker($sql, $params).PHP_EOL."Failed to register.", 'Failed: Register');

				http_response_code(200);
				$response = ['error' => null];
				$dest = "/auth/register";
			}

			break;
		
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
					$_SESSION['error'] = ['code' => 'invalid-credentials', 'username' => $username];
					
					http_response_code(400);
					$response = ['error' => "Mot de passe ou nom d'utilisateur incorrect"];
					$dest = "/auth/login";
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

				logger('/log/users.log', "Successfully logged in.", "Login");
				break;
				
			} else {
				http_response_code(200);
				$response = ['message' => "Utilisateur déjà connecté", 'username' => $username];
				$dest = "/";
				break;
			}
		
		case 'update-credentials':	// update credentials
			if ($_SESSION['CSRF'] !== $csrf || !$csrf) {
				http_response_code(401);
				$reponse = ["error" => "Unauthorized"];
				$dest = "/";
				break;
			}
			
			// extract saved data for user
			$query = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? AND auth_type = ?");
			$query->execute([$username, 'local']);
			$user = $query->fetch();
			
			// extract new values
			$new_username = $_POST['new-username'] ?? null;
			$new_password = $_POST['new-password'] ?? null;
			$new_email    = $_POST['new-email']    ?? null;

			if ($username !== $new_username) {// username update
					if ($username === 'admin') {
							$_SESSION['error'] = ['code' => 'locked-username'];
							
							http_response_code(400);
							$response = ['error' => "Nom d'utilisateurice non modifiable"];
							$dest = "/user/myaccount";
							break;
					}

					// Username validity
					if (!preg_match('/[a-zA-Z_]+[a-zA-Z0-9_-]{2,}/', $username)) {// entry found : username exists
						http_response_code(400);
						$response = ['error' => "Nom d'utilisateur invalid."];
						$_SESSION['error'] = "bad-pattern";
						$dest = "/auth/register?method=local";
						break;
					}
					
					$query = $pdo->prepare("UPDATE users SET username = :username WHERE id = :userid;");
					$query->execute([':username' => $new_username, ':userid' => $_SESSION['user_id']]);

					$_SESSION['username'] = $new_username;

					$response = ['message' => "Nom d'utilisateurice mis à jour pour $username"];

					logger('/log/users.log', "User $username renamed to $new_username.", "Credentials update");
					$username = $new_username;
			
			} if ($email !== $new_email) {// email update
					if ($username === 'admin') {
							$_SESSION['error'] = ['code' => 'locked-email'];
							
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
			
			} if ($password !== $new_password) {// password update
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
							$_SESSION['error'] = ['code' => 'invalid-credentials'];
							
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
		
		case 'reset-credentials':	// send password reset
			$resetToken = $_SESSION['reset-token'];
			
			if (!$resetToken) {// get reset token
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
					<div><a href='https://arcensiel.org/auth/reset-credentials?&token=$token'>Réinitialiser le mot de passe</a></div>

					<div style='margin-top:1em;'><small><i>Le lien est valide une heure. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.</i></small>
					</div>";
					
					// send mail
					$delivery = sendMail(to: [$email], subject: $subject, body: $body, confidential: true);
				}
				
				$_SESSION['tmp-data'] = ['mail' => $email];
				$resetstatus = 'triggered';
				break;
			
			} else {
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

				$dest = "/auth/login";
				break;
			}
		
		default:
			$connected = false;
		}
	
	if ($dest) {
		header("Location: $dest");
		echo json_encode($resetstatus);
		exit;
	}
}

// 
$error = $_SESSION['error'] ?? ['code' => null];
unset($_SESSION['error']);

$resetstatus ??= null;

$tmpdata = $_SESSION['tmp-data'] ?? null;
$_SESSION['tmp-data'] = [];

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
		<link rel='stylesheet' type='text/css' href='/files/assets/css/credentials.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/login.js'></script>
	</head>
	
	<body
		<?php if ($error): ?>
			data-error="<?= htmlspecialchars($error['code'], ENT_QUOTES) ?>";
		<?php endif; ?>
		<?php if ($message): ?>
			data-message="<?= htmlspecialchars($message, ENT_QUOTES) ?>";
		<?php endif; ?>
		>
		
		<?php include($root . '/app/templates/banner.html') ?>
		
		<div id="main">
			<div class="auth-form-wrap">
				<form id='auth-form' class='wrap' method='post' action='/auth/<?= $action ?>'>
					<h1 class="title">
						<?php if ($action === 'login'): ?>
							Se connecter
						<?php endif; ?>
						<?php if ($action === 'register'): ?>
							S'inscrire
						<?php endif; ?>
						<?php if ($action === 'reset-credentials'): ?>
							Réinitialiser son mot de passe
						<?php endif; ?>
					</h1>
					
					<div class='main-info'>
						<div>
							<?php if ($resetstatus === 'triggered'): ?>
								Si un compte est rattaché au mail <i><?= $tmpdata['mail'] ?></i>, un email contenant un lien de réinitialisation a été envoyé.
							<?php endif; ?>
							<?php if ($resetstatus === 'bad-token'): ?>
								Lien invalide.
							<?php endif; ?>
							<?php if ($resetstatus === 'old-token'): ?>
								Lien expiré. Effectuez une nouvelle demande de réinitialisation.
							<?php endif; ?>
						</div>
						
						<?php if (($resetToken || $action !== 'reset-credentials') && !$resetstatus): ?>
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
								<input class='lineentry decorated' type='text' name='username' id='username' autocomplete=on pattern="[a-zA-Z_]+[a-zA-Z0-9_-]{2,}" required placeholder="Nom d'utilisateur" title="Veuillez entrer votre nom d'utilisateur"
									<?php if ($error['code'] === 'invalid-credentials'): ?>
										value=<?= $error['username'] ?>
									<?php endif; ?>
									<?php if ($action === 'reset-credentials'): ?>
										value=<?= $tmpdata['user'] ?>
										readonly
									<?php endif; ?>
								>
								<div class='icon validity right'></div>
								<span class="spinner icon right"></span>
							</div>
							<div class='error username'>Nom d'utilisateur indisponible.</div>
							<div class='error pattern'>Nom d'utilisateur invalide.</div>
						</div>
						<?php endif; ?>
						
						<?php if ($action === 'register' || ((!$resetToken && $action === 'reset-credentials') && !$resetstatus)): ?>
						<div class='box email'>
							<div class='input-header'>
								<label class='label' for='email'>Adresse email</label>
							</div>
							<div class='input-container'>
								<div class='icon mail left'></div>
								<input class='lineentry decorated' type='email' name='email' id='email' autocomplete=on pattern="[a-z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" required placeholder='john.doe@example.com' title="Veuilez entrer votre adresse mail"
									<?php if ($error['code'] === 'used-mail'): ?>
										value=<?= $error['email'] ?>
									<?php endif; ?>
								>
								<div class='icon validity right'></div>
								<span class="icon spinner right"></span>
							</div>
							<div class='error mail'>Cette adresse mail existe déjà.</div>
						</div>
						<?php endif; ?>
						
						<?php if (($resetToken || $action !== 'reset-credentials') && !$resetstatus): ?>
						<div class='box password'>
							<div class='input-header'>
								<label class='label' for='pswd'>Mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left'></div>
								<input class='lineentry decorated livecheck' type='password' name='password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe" pattern='(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^\sa-zA-Z0-9]).{6,}'>
								<div class='icon toggle-pass right hoverable'></div>
							</div>
							<div class='error password' <?= $error['code'] === 'invalid-credentials' ? 'active':'' ?>><?= $action === 'login' ? "Nom d'utilisateur ou mot de passe incorrect":"Le mot de passe n'est pas valide" ?></div>
							<?php if ($action === 'login'): ?><div class='context right mail'><a class="dynamic" href='/auth/reset-credentials'>Mot de passe oublié ?</a></div><?php endif; ?>

							<?php if ($action === 'register' || $action === 'reset-credentials'): ?>
								<?php include($root . '/app/templates/password-requirements.html') ?>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						
						<?php if ($action === 'register' || (($action === 'reset-credentials' && $resetToken) && !$resetstatus)): ?>
						<div class='box password-confirm'>
							<div class='input-header'>
								<label class='label' for='pswd-confirm'>Confirmation du mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left'></div>
								<input class='lineentry decorated' type='password' id='pswd-confirm' autocomplete=off required placeholder='Confirmation du mot de passe' title="Veuilez confirmer votre mot de passe">
								<div class='icon toggle-pass bis right hoverable' title='Montrer le mot de passe'></div>
							</div>
							<div class='error confirmation'>Les mots de passe ne correspondent pas !</div>
						</div>
						<?php endif; ?>
					</div>
					
					
					<div class='actions-wrap'>
						<?php if ($action === 'login'): ?>
							<div class='action-container'>
								<button class='action-btn' type='submit' name='action' value='login'>Se connecter</button>
							</div>
						<?php endif; ?>
						<?php if ($action === 'register'): ?>
							<div class='action-container'>
								<button class='action-btn register' name='action' value='register'>S'inscrire</button>
							</div>
						<?php endif; ?>
						<?php if ($action === 'reset-credentials' && !$resetstatus): ?>
							<div class='action-container'>
								<button class='action-btn reset' name='action' value='reset-credentials'><?= $resetToken ? 'Réinitialiser':'Continuer' ?></button>
							</div>
						<?php endif; ?>
					</div>
					
				</form>
				
				<?php if ($action === 'register' || $action === 'login'): ?>
				<div class='external actions-wrap'>
					<?php if ($action === 'login'): ?>
						<div class='action-container'>
							<span class='label'>Pas encore de compte ?</span>
							<button class='action-btn'><a href='/auth/register'>S'inscrire</a></button>
						</div>
					<?php endif; ?>
					<?php if ($action === 'register'): ?>
						<div class='action-container'>
							<span class='label'>Déjà un compte ?</span>
							<button class='action-btn'><a href='/auth/login'>Se connecter</a></button>
						</div>
					<?php endif; ?>
					<?php if (false): ?>
						<div class='action-container'>
							<button class='action-btn'>
								<a href='/auth/loginCAS'>Se connecter avec ENS de Lyon</a>
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
									if (body.dataset.error === 'used-login') {
										Error('username').show()
									}
									if (body.dataset.error === 'used-mail') {
										Error('mail').show()
									}
									if (body.dataset.error === 'bad-pattern') {
										Error('pattern').show()
									}
									if (body.dataset.error === 'invalid-credentials') {
										Error('password').show()
									}
									if (body.dataset.message === 'registered') {
										notify('Bienvenue !', 'Compte créé avec succès.', 'green')
									}
								}
		
		
	<?php if ($action === 'register' || ($action === 'reset-credential' && ($resetToken && !$resetstatus))): ?>
	// Regsiter section logic //
		// Password checks
		Input('pswd-confirm').oninput = pswdCheck
		Input('pswd').oninput = pswdRequirementsCheck
		
		// (in)activate button
		function submitAllower(force = false) {
				Buttons(':is(.register,.reset)').forEach(btn => btn.disabled = force || (Error('[active]') !== null || document.querySelector('.check-item[invalid]') !== null))
		}
		
		// username/email check
		Input('email').oninput = compareWithOldValue
		Input('email').onchange = e => checkInput(e.target, '/auth/login', 'email')
		
		Input('username').oninput = compareWithOldValue
		Input('username').onchange = e => checkInput(e.target, '/auth/login', 'username')
	<?php endif; ?>
		</script>
	</body>
</html>