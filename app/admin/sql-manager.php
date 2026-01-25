<?php
session_start();

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
   echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

if ($_SESSION['username'] !== 'admin') {
    http_response_code(403);
    echo "<h1>Forbidden</h1>You don't have permission to access this resource.";
	exit;
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Gestion de la base de données</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/jsonview.js'></script>
		
		<style>
			form {
					/* border: 3px solid black; */
					border-radius: 6px 0 0 6px;
					padding: 1.25em;
					background: white;
					color: black;
					transition: right .4s ease-in-out;
					}
					
			.spinner {
				--dim: var(--standard-size);
				display: block;
				};
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="">
				<div class="head-info">
					<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"><div class='img'></div></a>
					<h1>Gestion de la base de données</h1>
				</div>
				
				<form id='sql-form'>
					<div class='main-info'>
						<div class='box username'>
							<div class='input-header'>	
								<label class='label' for='username'>Requête SQL</label>
							</div>
							<div class='input-container'>
								<textarea class='textentry' rows=7 type='text' name='sql-query' id='sql-query' autocomplete=off required placeholder="SQL query" title="Veuillez entrer votre requête SQL"></textarea>
							</div>
						</div>
					</div>
					
					<input name='csrf-token' data-csrf=<?= $_SESSION['CSRF'] ?> value=<?= $_SESSION['CSRF'] ?> hidden>
					
					<div class='form-action-wrap'>
						<div class='form-action'>
							<button class='action-btn spinnable' type='submit' id='submit'><span class='text'>Soumettre</span><span class="spinner"></span></button>
						</div>
						
						<div class='form-action'>
							<div class='action-btn reset'>Réinitialiser</div>
						</div>
					</div>
				</form>
				
				<div class='output'>
					<div class='sql-response'>
						<div class='label'>Résultat</div>
						<div class='output-text clean'></div>
					</div>
					
					<div class='sql-response' style='display: none'>
						<div class='label'>Raw output</div>
						<div class='output-text raw'></div>
					</div>
				</div>
			</div>
		</div>
		
		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>
		document.querySelector('form#sql-form').onsubmit = (e) => {sqlSend(e)};
		async function sqlSend(e) {
						e.preventDefault();
						
						// spinner in submit button
						btn = document.querySelector('#submit');
						btn.disabled = true;
						btn.toggleAttribute('active');
						
						// extract form information
						formData = new FormData(e.target);
					    
						// Response object
						response = await fetch("/admin/engine/sql", {
									method: "POST",
									body: formData
									});
						
						// Response body content
						data = await response.json(); // dict parsed response data
						
						if (!response.ok) {// http code 400-500 reuturn false
										alert(`Error: HTTP ${response.status}: ${data.error}`);
										console.log(data);
										console.log(data.error);
										return null
										}
						
						output = document.querySelector('.output .output-text.clean');
						raw = document.querySelector('.output .output-text.raw');
						
						output.innerHTML = '';
						raw.innerHTML = '';
						output.removeAttribute('error');
						
						raw.parentNode.style.display = 'none';
						
						if (response.status === 250) {// failed sql request
							// PDOException outputs a dict with key 'errorInfo' whose value is a (3-tuple)
							// Data structure is response [Response object] => .json() [body content] => .error [key of dict sent by php script] => .errorInfo [key of dict paired with key 'error'] => [2] [third value of array paired with 'errorInfo' key]
							errorData = data.error.errorInfo[2];
							errorCode = data.error.errorInfo[0];
							
							output.innerHTML = `SQL Error [${errorCode}]: ${errorData}.`;
							output.setAttribute('error','');
							
							btn.disabled = false;
							btn.toggleAttribute('active');;
						
							return
						} else {// correct sql response
							sqlData = data['sql-data'];
						}
						
						raw.innerText = JSON.stringify(sqlData);
						
						jsonview.renderJSON(JSON.stringify(sqlData), output)

						raw.parentNode.style.display = 'initial';
						
						btn.disabled = false;
						btn.toggleAttribute('active');;
					}
		
		document.querySelector('.action-btn.reset').onclick = () => {
						output = document.querySelector('.output .output-text.clean');
						raw = document.querySelector('.output .output-text.raw');
						input = document.querySelector('#sql-query');
						
						output.innerHTML = '';
						raw.innerHTML = '';
						input.value = '';
						}
		</script>
	</body>
</html>