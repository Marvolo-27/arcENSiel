<?php
// Database connection and common variables
require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.php";

// Restrict to admins
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<h1>Unauthorized</h1>The requested ressource is not accessible.";
	exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
	exit;
}

?>

<!DOCTYPE html>
<html>
	<head>
		<title>Mails</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Logs">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		<link rel='stylesheet' type='text/css' href='/files/assets/css/tables.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
		<script src='/files/assets/js/tables.js'></script>
		
		<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
		<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
			<div class="head-info">
				<a class='icon go-back inline large hoverable dynamic' href="/admin/dashboard"></a>	
				<h1>Mails</h1>
				<div class='icon refresh inline large hoverable dynamic' title="Synchroniser les mails" data-type="mails"></div>
			</div>
			
			<div class="box filters">
				<div class="input-container checkboxes">
					<span>
						<input class='checkbox' type='radio' id="all-btn" name="table" value="all" checked>
						<label class='tag-item option blue' for="all-btn">Tout</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="sent-btn" name="table" value="sent">
						<label class='tag-item option blue' for="sent-btn">Envoyés</label>
					</span>
					<span>
						<input class='checkbox' type='radio' id="received-btn" name="table" value="received">
						<label class='tag-item option blue' for="received-btn">Reçu</label>
					</span>
				</div>
			</div>
			
			<input id='csrf' data-csrf=<?= $_SESSION['CSRF'] ?> hidden readonly>
			<div class='table no-bound' id='mails' active></div>
		</div>
		
		<div class="body-overlay" id="overlay-preview">
			<div class="inner-overlay">
				<div class="file-preview" active><img><iframe class="activable"></iframe></div>

				<div class='external actions-wrap linear'>
					<div class='action-container'>
						<button class='action-btn close'>Fermer</button>
					</div>
				</div>
			</div>
		</div>

		<div class="body-overlay" id="overlay-viewmail">
			<div class="inner-overlay">
				<div class="mail-headers">
					<div>De : <span class="maildata from"></span></div>
					<div>À : <span class="maildata to"></span></div>
					<div>Cc : <span class="maildata cc"></span></div>
					<div>Date : <span class="maildata time"></span></div>
				</div>

				<div class="mail-preview" style="margin: 1.25em 0"><iframe sandbox style="width: 100%"></iframe></div>

				<div class="mail-attachments"><ul></ul></div>

				<div class='external actions-wrap linear'>
					<div class='action-container'>
						<button class='action-btn close'>Fermer</button>
					</div>
				</div>
			</div>
		</div>

		<?php include($root . '/app/templates/footer.html') ?>
		
		<script>		
			// on-load logic
			window.addEventListener('load', loader)
			async function loader()  {
						await tablesBuilder()

						// Tables buttons management
						Box('filters').inputs().forEach(item => item.onchange = e => {
								const token = e.target.value
								
								if (token === 'all') {
									mailsTable.clearFilter()
								} else {
									mailsTable.setFilter("type", "=", token)
								}
							})
						}
			
			async function tablesBuilder() {
					// fetch all required data
					await fetchMails('#mails')
					
					// easy match table with their type
					tableMatch = {
								'mails': {'table': mailsTable, 'source': JSON.stringify(mailsTable.getData()), 'call': fetchMails, 'selector': '#mails', 'type': 'mails'},
								}
					activeTable = tableMatch.mails // Force active table update
					
					tablesSwitcher()
			}

			// Sync mails
			Icon('refresh').onclick = viewerSync
			
			// Browse mail
			function viewMail(data) {
					const headers = Overlay('viewmail').section('mail-headers')
					const preview = Overlay('viewmail').section('mail-preview')
					const attachments = Overlay('viewmail').section('mail-attachments').child('ul')
					
					preview.child('iframe').srcdoc = data.content
					headers.section('maildata.from').innerText = data.sender
					headers.section('maildata.to').innerText = data.recipients
					headers.section('maildata.cc').innerText = data.cc
					headers.section('maildata.time').innerText = data.time

					Array.from(Object.values(JSON.parse(data.attachments) ?? [])).forEach(file => {
						const [group, extension] = file.mime.split('/')
						const view = (group === 'application' && ['pdf', 'sql'].includes(extension)) || group === 'text' || group === 'image'
						
						const bytes = Uint8Array.fromBase64(file.content)
						const blob = new Blob([bytes], {type: file.mime})
						const url = URL.createObjectURL(blob)

						var li = document.createElement('li')
						li.innerHTML = `
							<div class='icon inline ${view ? 'visualize inline hoverable dynamic':''}' ${view ? 'title="'+file.filename+'"':''}></div>
							<div class='icon download  inline hoverable dynamic' title="${file.filename}"></div>
							<div class='file-element' data-url="${url}" data-mime="${file.mime}">${file.filename}</div>
						`
						li.querySelector('.file-element').addEventListener('click', e => e.target.parent('.file-element').toggle('selected'))
						li.icon('visualize')?.addEventListener('click', e => viewFile(...Object.values(e.target.parent('li').querySelector('.file-element').dataset), 'viewmail'))
						li.icon('download').addEventListener('click', async e => download(await fetchBlob(e.target.parent('li').querySelector('.file-element').dataset.url), e.target.getAttribute('title')))

						attachments.append(li)
						}
					)

					Overlay('viewmail').toggle()
				}
			
			// close mail viewer
			Overlay('viewmail').button('close').onclick = e => {
					e.target.parent('overlay').toggle()
					e.target.parent('overlay').section('mail-preview').child('iframe').srcdoc = '';
					e.target.parent('overlay').section('mail-headers').sections('maildata').forEach(tag => tag.innertText = '')
					e.target.parent('overlay').section('mail-attachments').child('ul').innerHTML = '';
			}
		
			// close file viewer
			Overlay('preview').button('close').onclick = e => {
					e.target.parent('overlay').toggle()
					e.target.parent('overlay').section('file-preview').child('img').src = '';
					e.target.parent('overlay').section('file-preview').child('iframe').src = '';
					e.target.parent('overlay').section('file-preview').child('iframe').hide()

					activeOverlay = pendingAction.overlay
					Overlay(activeOverlay)?.toggle()
					pendingAction = {}
			}
		</script>
	</body>
</html>