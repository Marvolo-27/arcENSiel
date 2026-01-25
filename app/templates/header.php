<header id='banner'>
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
</header>

<nav id='nav'>
	<div class="nav-logo-wrap">
		<div class="nav-logo logo hide">
			<a href='/'>
				<img src="/files/icons/logo_arcensiel.svg">
			</a>
		</div>
	</div>
	
    <div class='nav-wrap'>
		<div class='nav-toggle' onclick='toggleNav(event.target)'>
			<img src='/files/icons/burger.svg' class='toggle-img'>
		</div>
        <div class='headers' hidden='true'>
            <span class='nav-item home-item'>
                <a class='nav-item-name' href='/actualites'>
                    Actualités
                </a>
            </span>
			<span class='nav-item'>
                <a class='nav-item-name'>
                    L'association
                </a>
				
				<span class="sub-menu" hidden='true'>
					<span class='sub-nav-item'>
						<a class='nav-item-name'>
							Qui sommes-nous ?
						</a>
					</span>
					<span class='sub-nav-item'>
						<a class='nav-item-name'>
							Nos activités
						</a>
					</span>
					<span class='sub-nav-item'>
						<a class='nav-item-name' href='/documents'>
							Statuts et archives
						</a>
					</span>
				</span>
            </span>
			<span class='nav-item'>
                <a class='nav-item-name'>
                    Ressources
                </a>

				<span class="sub-menu" hidden='true'>
					<span class='sub-nav-item' href='/ressources'>
						<a class='nav-item-name'>
							Documents utiles
						</a>
					</span>
					<span class='sub-nav-item'>
						<a class='nav-item-name' href="/archives">
							Historique
						</a>
					</span>
				</span>
            </span>
			<span class='nav-item'>
                <a class='nav-item-name' href='tombola.html'>
                    Nous contacter
                </a>
            </span>
			<span class='nav-item'>
                <div class='nav-item-icon'>
                    <a class='icon search inline medium hoverable dynamic invertable base-shdw' href="/search"><span class='img'></span></a>
                    <!-- <a class='icon history inline medium hoverable dynamic invertable' href="/archives"><span class='img'></span></a> -->
				</div>
            </span>
        </div>
    </div>

	<div class="nav-logo-wrap">
		<div class="nav-logo login">
			<img src='/files/icons/account.svg' class='toggle-img'>
		</div>
		
		<div class="auth-wrap">
			<div class='icon float cross small'><span class='img'></span></div>
			
			<?php if (!isset($_SESSION['user_id'])): ?>
				<form id='auth-form' method='post' action='/login/login.php?auth=local'>
					<div class="head-info">
						Connectez-vous
					</div>
					
					<div class='main-info'>
						<div class='box username'>
							<div class='input-header'>
								<label class='label' for='username'>Nom d'utilisateur</label>
							</div>
							<div class='input-container'>
								<div class='icon username left medium'><span class='img'></span></div>
								<input class='lineentry decorated' type='text' name='username' id='username' autocomplete=on required placeholder="Nom d'utilisateur" title="Veuillez entrer votre nom d'utilisateur">
							</div>
						</div>
						
						<div class='box password'>
							<div class='input-header'>
								<label class='label' for='pswd'>Mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left medium'><span class='img'></span></div>
								<input class='lineentry decorated' type='password' name='password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe">
								<div class='icon toggle-pass right medium hoverable'><span class='img' title='Montrer le mot de passe'></span></div>
							</div>
						</div>
					</div>
					
					<div class='form-action-wrap'>
						<div class='form-action'>
							<button class='action-btn' name='action' value='login' type='submit'>Se connecter</button>
						</div>
						
						<div class='form-action'>
							<span class='label small'>Pas de compte ?</span>
							<button class='action-btn'><a href='/register'>S'inscrire</a></button>
						</div>
					</div>
				</form>
			<?php endif; ?>
			
			<?php if (isset($_SESSION['user_id'])): ?>
				<div class="panel-head">
					Bienvenue <?= $_SESSION['username'] ?> !
				</div>
				
				<div class="panel-main">
					<a href='/user' class='dash-link'>Mon profil</a>
				</div>
				
				<?php if ($_SESSION['role'] == "admin"): ?>
					<div class="panel-main">
						<a href='/admin' class='dash-link'>Dashboard</a>
					</div>
				<?php endif; ?>
				
				<input class='token' data-csrf=<?= $_SESSION['CSRF'] ?> hidden>
			<?php endif; ?>
			
			<?php if (isset($_SESSION['user_id'])): ?>
			<div class='form-action-wrap'>
				<div class='form-action'>
					<a class="action-btn" href='/logout'>Se déconnecter</a>
				</div>
			</div>
			<?php endif; ?>
			
			<?php if (false && !isset($_SESSION['user_id'])): ?>
			<div class='form-action-wrap'>
				<div class='form-action'>
					<button class='action-btn'>
						<a href='/login/login.php?auth=CAS'>Se connecter avec ENS de Lyon</a>
					</button>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</nav>