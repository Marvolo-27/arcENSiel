window.addEventListener('load', listeners)
function listeners()  {
        // allow drag and drop by preventing default behaviors
        Section('drag-zone').ondragenter = allowDrop
        Section('drag-zone').ondragleave = allowDrop
    	Section('drag-zone').ondragover = e => e.preventDefault()
		Section('drag-zone').ondrop = fileUploadManage
		Section('visual').ondrop = fileUploadManage
        Section('visual').onclick = () => Section('frame').input().click()
		Section('frame').button('new-file').onclick = () => Section('frame').input().click()
		Input('file').onchange = e => fileUploadManage(e, false)
	}		


function allowDrop(e) {
		e.preventDefault()
		Section('frame').section('drag-zone').classList.toggle('dragged')
	}

function fileUploadManage(e, drop=true) {
		if (drop) allowDrop(e)
		
		// Layout and data components
		const error = Error('file')
		const frame = Section('frame')
		const input = Input('file')
		const overlay = Overlay('metadata') ?? Overlay('uploader')
		const files = drop ? e.dataTransfer.files : input.files
		const file = files[0]
		const pattern = new RegExp(input.dataset.format ?? input.dataset.accept?.replaceAll(',','|') ?? 'image/*')
		const maxsize = parseInt(input.dataset.size ?? 2)

		let invalid = false
		if (drop) input.files = files			// Set data to input
		
		// Validators
		if (files.length === 0) {// quit if no file selected
			return false
		} else if (files.length > 1) {
			invalid = 'Déposez un seul fichier.'
		} else if (!pattern.test(file.type)) {// invalid file type
			invalid = `Type de fichier non supporté : .${file.name.extension()}`;
		} else if (file.size > maxsize*1024*1024) {// invalid file size
			invalid = 'Taille du fichier trop élevée.'
		}
		
		if (invalid) {
			error.innerText = invalid
			error.show()
			frame.hide()
			input.value = '';
		} else {
			error.hide()
			frame.show()
			overlay.show()

			overlay.input('filename').readOnly = false
			overlay.input('filename').value = file.name.filename().filenameForm()
			overlay.input('filename').dataset.name = file.name.filenameForm()
			overlay.input('fileextension').value = file.name.extension().filenameForm()
			overlay.input('desc').value = ''
		}
		
		if (!!Overlay('metadata')) {
			Box('pic').section('visual').src = URL.createObjectURL(file)
		} else {
			overlay.child('.selected-file').innerText = file.name
		}

		return !invalid
	}		

function resetFileUpload(e, hide=false) {
		const overlay = Overlay('metadata') ?? Overlay('uploader')
		const frame = Section('frame')
		const file = frame.input('file')
		const filename = overlay.input('filename')

		frame.hide()
		if (hide) overlay.hide()
		
		overlay.child('form').reset()
		file.required = true
		filename.readOnly = false
		file.value = ''
		filename.dataset.name = ''
		if (Overlay('uploader')) overlay.child('.selected-file').innerText = ''
	}

function filePickManage() {
		const source = Overlay('filepicker')
		const overlay = Overlay('metadata') ?? Overlay('uploader')
		const frame = Section('frame')
		const error = source.error('file-choice')
		const file = source.child('.file-element[selected]')
		const upload = frame.input('file')
		const url  = file?.dataset.targetUrl
		
		if (file === null) {
			error.innerText = 'Sélectionnez un fichier pour continuer';
			error.show()
			return false
		} else {
			error.hide()
			source.hide()
			frame.show()
			overlay.show()
			source.childs('.file-element').forEach(tag => tag.unset('selected'))
			
			// Set data
			overlay.input('filename').dataset.name = url.filename(true).filenameForm()
			overlay.input('filename').value = url.filename().filenameForm()
			overlay.input('fileextension').value = url.extension().filenameForm()
			overlay.input('desc').value = file.dataset.alt
			overlay.input('filename').readOnly = true
			upload.required = false
			
			return url
		}
	}

async function fileSelectorLoad(type=null) {
		const container = Overlay('filepicker').section('files-list-container')
		container.innerHTML = '<div class="spinner" active></div>'

		const jsonData = JSON.stringify({'csrf':csrfToken, 'action':'fetch', 'filetype':type})
		const response = await fetchAPI('/admin/engine/files', jsonData)
		
		container.innerHTML = ''

		response.body.forEach(file => {
			const wrap = document.createElement('div')
			const div = document.createElement('div')

			wrap.classList.toggle('file-element-wrap')
			div.dataset.targetUrl = file.url
			div.dataset.alt = file.description
			div.dataset.references = file.categories ?? ''
			div.innerText = file.name
			div.classList.toggle('file-element')
			div.onclick = e => fileUpdateSelection(e, type === 'image')

			wrap.append(div)
			container.append(wrap)
		})
	}

function fileUpdateSelection(e, responsive=false) {
		Overlay('filepicker').childs('.file-element').forEach(tag => tag.unset('selected'))
		const target = e.target
		target.set('selected')

		if (responsive) {
			const preview = Overlay('filepicker').child('.file-preview')
			preview.firstElementChild.src = target.dataset.targetUrl
			preview.firstElementChild.setAttribute('alt', target.dataset.alt)
		}
	}