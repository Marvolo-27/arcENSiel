<div class="body-overlay" id="overlay-uploader">
	<div class="inner-overlay">
		<div>
			<h4 class="title">
				Ajouter un fichier
			</h4>
		</div>

		<form id="overlay-add-form">
			<div class='box pic'>
				<div class='frame input-container'>
					<div class='drag-zone centered'>
						<span>
							<div>Déposez un fichier pour l'ajouter aux documents accessibles.</div>
							<div class='file-formats'><small>Formats supportés : <?= $mode === 'global' ? "image, .pdf, .sqlite, .zip" : ".pdf" ?></small></div>
							<div class='file-maxsize'><small>Taille maximale : <?= $mode === 'global' ? "10 Mo" : "2 Mo" ?></small></div>
						</span>
						
						<div class="actions-wrap">
							<div class="action-container">
								<div class='action-btn new-file'>Choisir un nouveau fichier</div>
							</div>
							<?php if($mode !== 'global'): ?>
							<div class="action-container">
								<div class='action-btn exist-file'>Choisir un fichier existant</div>
							</div>
							<?php endif; ?>
						</div>
						
						<div class='overlay-panel rounded frame-overlay centered'>
							<span>Lâcher le fichier pour l'ajouter</span>
							<div class='icon extra-large inline file'><span class='img'></span></div>
						</div>
					</div>
					<div class="visual">
						<small>Fichier sélectionné : <span class="selected-file"></span></small>
						<div class='icon inline delete dynamic hoverable'><span class='img'></span></div>
					</div>
					<input class='fileentry hidden' type='file' accept="<?= $mode === 'global' ? 'application/zip,application/pdf,application/vnd.sqlite3,image/*' : 'application/pdf' ?>" name='file' id='file' data-format="<?= $mode === 'global' ? '(application/(pdf|zip|x-zip-compressed|vnd\\.sqlite3)|image/.*)' : 'application/pdf' ?>" data-size="<?= $mode === 'global' ? 10 : 2 ?>" autocomplete=off placeholder="Affiche" title="Veuillez sélectionner un visuel pour l'évènement" required>
				</div>
				<div class='error file'></div>
			</div>
			
			<div class='box filename'>
				<div class='input-header'>
					<label class='label' for='filename'>Nom du fichier</label>
				</div>
				<div class='input-container'>
					<input class='lineentry small' type='text' name='filename' id='filename' autocomplete=off placeholder="Nom du fichier" title="Nom du fichier" required>
					<span>.</span>
					<input class='lineentry small' type='text' name='fileextension' id='fileextension' autocomplete=off placeholder="ext" title="Extension du fichier" style="width: 5em" required disabled>
				</div>
			</div>
			
			<div class='box description'>
				<div class='input-header'>
					<label class='label' for='desc'>Description pour le fichier</label>
				</div>
				<div class='input-container'>
					<input class='lineentry' type='text' name='desc' id='desc' autocomplete=off placeholder="Description" title="Description pour le fichier" required>
				</div>
			</div>
			
			<?php if($mode === 'global'): ?>
			<div class="checkbox-wrap">
				<div class='input-container'>
					<input class='checkbox' type='checkbox' name='admin-check' id='admin-check' autocomplete=off title="Restreindre l'accès aux administrateurs"><label class='label' for='admin-check'>Restreindre l'accès aux administrateurs</label>
				</div>
			</div>
			<?php endif; ?>
			
			<div class='external actions-wrap linear'>
				<div class='action-container'>
					<button class='action-btn validate' type='submit'>Poursuivre</button>
				</div>
				<div class='action-container'>
					<div class='action-btn cancel'>Annuler</div>
				</div>
			</div>
		</form>
	</div>
</div>