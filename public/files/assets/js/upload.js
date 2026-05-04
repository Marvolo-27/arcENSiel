window.addEventListener('load', listeners);
function listeners()  {
            // allow drag and drop by preventing default behaviors
            document.querySelector('.drag-zone').addEventListener('dragenter', allowDrop);
            document.querySelector('.drag-zone').addEventListener('dragleave', allowDrop);

            document.querySelector('.drag-zone').ondragover = e => {e.preventDefault()}	// fires when a draggable object is over valid drag zone (repeats)

            document.querySelector('.frame .action-btn').onclick = e => {e.target.findParent('.frame').querySelector('input').click()}	// fires when a draggable object is over valid drag zone (repeats)

            document.querySelector('.drag-zone').ondrop = fileDropHandle;
		}		


function allowDrop(e) {
						e.preventDefault();
						e.target.findParent('.frame').querySelector('.drag-zone').classList.toggle('dragged')
						}

function fileDropHandle(e) {
						allowDrop(e);
						
						data = e.dataTransfer;
						files = data.files;
						
						input = Input('file');
						input.files = files;
						fileUploadManage(input, /application\/pdf/, 2)
						}


// check selected file validity
function fileUploadManage(input, format=/image\/.*/, maxsize = 2) {
			tag = input;

			// extract files info
			files = tag.files;
			if (files.length === 0) {return false} // quit if no file selected
			
			// extract file data
			file = files[0];
			
			invalid = '';
			if (files.length > 1) {
				invalid = 'Déposez un seul fichier.';
			} else if (file.type.match(format) === null) {// invalid file type
				invalid = `Type de fichier non supporté : .${file.name.split('.')[file.name.split('.').length - 1]}`;
			} else if (file.size > maxsize*1024*1024) {// invalid file size
				invalid = 'Taille du fichier trop élevée.';
			}
			
			error = Error('file');
			frame = document.querySelector('.frame');
			if (invalid !== '') {
				error.innerText = invalid;
				error.show();
				frame.hide();
				tag.value = '';
				return false;
			} else {
				error.hide()
				Overlay('uploader').child('.selected-file').innerText = file.name;
			}
			
			frame.show();
			return true
}