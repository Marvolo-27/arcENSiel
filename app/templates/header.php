<?php require('banner.html') ?>

<nav id='nav'>
	<div class="nav-logo-wrap">
		<div class="nav-logo logo">
			<a href='/'>
				<img src="/files/icons/logo_arcensiel.svg">
			</a>
		</div>
	</div>
	
    <div class='nav-wrap'>
		<div class='nav-toggle' onclick='toggleNav(event.target)'>
			<img src='/files/icons/burger.svg' class='toggle-img'>
		</div>
        <div class='headers'>
            <a class='nav-item home-item' href='/actualites'>
                <span class='nav-item-name'>
                    Actualités
				</span>
			</a>
			<span class='nav-item'>
                <span class='nav-item-name'>
                    L'association
				</span>
				
				<span class="sub-menu">
					<a class='sub-nav-item'>
						<span class='nav-item-name'>
							Qui sommes-nous ?
						</span>
					</a>
					<a class='sub-nav-item'>
						<span class='nav-item-name'>
							Nos activités
						</span>
					</a>
					<a class='sub-nav-item' href='/documents'>
						<span class='nav-item-name'>
							Statuts et archives
						</span>
					</a>
				</span>
            </span>
			<span class='nav-item'>
                <span class='nav-item-name'>
                    Ressources
				</span>

				<span class="sub-menu">
					<a class='sub-nav-item' href='/ressources'>
						<span class='nav-item-name'>
							Documents utiles
						</span>
					</a>
					<a class='sub-nav-item' href="/archives">
						<span class='nav-item-name'>
							Historique
						</span>
					</a>
				</span>
            </span>
			<a class='nav-item' href='/contact'>
                <span class='nav-item-name'>
                    Nous contacter
				</span>
			</a>
			<span class='nav-item'>
                <div class='nav-item-icon'>
                    <a class='icon search inline hoverable dynamic invertable base-shdw' href="/search"></a>
				</div>
            </span>
        </div>
    </div>

	<div class="nav-logo-wrap">
		<div class="nav-logo login" active>
			<img src='/files/icons/account.svg' class='toggle-img'>
		</div>
		
		<div class="auth-wrap">
			<div class='icon float cross small'></div>
			
			<?php if (!isset($_SESSION['user_id'])): ?>
				<form id='auth-form' method='post' action='/auth/login?method=local'>
					<div class="head-info">
						Connectez-vous
					</div>
					
					<div class='main-info'>
						<div class='box username'>
							<div class='input-header'>
								<label class='label' for='username'>Nom d'utilisateur</label>
							</div>
							<div class='input-container'>
								<div class='icon username left'></div>
								<input class='lineentry decorated' type='text' name='username' id='username' autocomplete=on required placeholder="Nom d'utilisateur" title="Veuillez entrer votre nom d'utilisateur">
							</div>
						</div>
						
						<div class='box password'>
							<div class='input-header'>
								<label class='label' for='pswd'>Mot de passe</label>
							</div>
							<div class='input-container'>
								<div class='icon lock left'></div>
								<input class='lineentry decorated' type='password' name='password' id='pswd' autocomplete=off required placeholder="Mot de passe" title="Veuilez entrer votre mot de passe">
								<div class='icon toggle-pass right hoverable' title='Montrer le mot de passe'></div>
							</div>
							<div class='context right mail'><a class="dynamic" href='/auth/reset-credentials'>Mot de passe oublié ?</a></div>
						</div>
					</div>
					
					<div class='actions-wrap'>
						<div class='action-container'>
							<button class='action-btn' name='action' value='login' type='submit'>Se connecter</button>
						</div>
						
						<div class='action-container'>
							<span class='label small'>Pas de compte ?</span>
							<button class='action-btn'><a href='/auth/register'>S'inscrire</a></button>
						</div>
					</div>
				</form>
			<?php endif; ?>
			
			<?php if (isset($_SESSION['user_id'])): ?>
				<div class="panel-head">
					Bienvenue <?= $_SESSION['username'] ?> !
				</div>
				
				<div class="panel-main">
					<a href='/user' class='dynamic illustrated post external'>Mon profil</a>
				</div>
				
				<?php if ($_SESSION['role'] == "admin"): ?>
					<div class="panel-main">
						<a href='/admin' class='dynamic illustrated post external'>Dashboard</a>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<?php if (isset($_SESSION['user_id'])): ?>
			<div class='actions-wrap'>
				<div class='action-container'>
					<a class="action-btn" href='/auth/logout'>Se déconnecter</a>
				</div>
			</div>
			<?php endif; ?>
			
			<?php if (false && !isset($_SESSION['user_id'])): ?>
			<div class='actions-wrap'>
				<div class='action-container'>
					<button class='action-btn'>
						<a href='/auth/login?method=CAS'>Se connecter avec ENS de Lyon</a>
					</button>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</nav>