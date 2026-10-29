//// TABLES POINTERS
let activeTable = {}
let tableMatch = {}
let autoEditedCells = {}

let usersTable
let articlesTable
let tagsTable
let typesTable
let filesTable
let categoriesTable
let haTable
let goodiesTable
let dq7Table
let groupsTable
let subscriptionsTable
let stocksTable

// Matching tables {slug:name, }
const haTypeMatch = {'Shop':'Boutique', 'Event':'Billeterie', 'Donation':'Don', 'Membership':'Adhésion'}
const statusMatch = {'published': 'Publié', 'archived': 'Archivé', 'draft': 'Brouillon'}
const goodiesList = ["Pin's arcENSiel", "Pin's Mouflon", "Sticker", "Briquet", "Affiche"]
const pricesList  = ["Prix conseillé", "Prix minimum", "Prix libre", "Paiement en espèces", "Liste d'attente"]

//// Utils
function tablesListener(watcher=false, selector='.input-container') {
		tablesSwitcher()

		// Tables buttons management
		Array.from(document.querySelector(selector).children).forEach(
				item => {
					item.addEventListener('change', e => {
						if (!!e.target.parent('.subsection')) return // Prevent nested buttons from triggering a switch

						tkn = e.target.parent('span').input().value;
						toggleTable(tkn)
						activeTable=tableMatch[tkn];

						if (watcher) dataChecker(activeTable.table.getData())
					})
				})
}

function tablesSwitcher() {
		Object.values(tableMatch).forEach(value => value.table.on("tableBuilt", () => value.source = JSON.stringify(value.table.getData())))
		Object.values(tableMatch).forEach(value => value.table.on("dataChanged", data => dataChecker(data)))
		Object.values(tableMatch).forEach(value => value.table.on("cellEdited", cell => cell.getValue() !== cell.getInitialValue() ? cell.getElement().set('changed'):cell.getElement().unset('changed')))
}

function dataChecker(data) {
			if (JSON.stringify(data) === tableMatch[activeTable.type].source) {
				Button('save').hide()
				Button('reset').hide()
				activeTable.integrity = true;
			} else if (activeTable.table.validate() !== true) {
				Button('save').hide()
				Button('reset').hide()
				activeTable.integrity = false;
			} else {
				Button('save').show()
				Button('reset').show()
				activeTable.integrity = false;
			}
		}

//// TABULATOR GENERATORS
// Files table
async function fetchFiles(target) {
			const sourcedata = await dataFetcher('files', null, 'files');
			const users = await dataFetcher('users').then(response => response.reduce((list, user) => {list[user.username] = user.username; return list}, {}));

			filesTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataStretch",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				initialSort:[
					{column:"name", dir:"asc"}
				],
				columns: [
					{ title: "", field: "actions", mutator: (value, data) => data.url, formatter: function (cell) {
							rowdata = cell.getRow().getData();
							v_type = rowdata.type.match(/(application\/pdf|image\/.*)/)
							return `<span>
										<div class='icon view inline ${rowdata.status && v_type ? 'visualize dynamic hoverable':''}' ${rowdata.status && v_type ? 'title="Voir le fichier"':''}></div>
										<div class='icon delete inline dynamic hoverable' title="Supprimer ${rowdata.status ? 'le fichier':"l'entrée"}"></div>
										<div class='icon inline ${rowdata.status ? 'download dynamic hoverable':''}' title="Télécharger le fichier"></div>
									</span>`}, cellClick: (e, cell) => actionSwitch(e, cell), resizable: false, frozen: true},
					{ title: "Nom du fichier", field: "name", mutator: value => value.filename().filenameForm(), formatter: cell => cell.getValue() + '.' + cell.getRow().getData().extension, editor: 'input', validator: ['unique', 'required'], vertAlign: 'middle'},
					{ title: "Nom initial", field: "basename", visible: false, mutator: (value, data) => data.url.filename()},
					{ title: "Extension", field: "extension", visible: false, mutator: (value, data) => data.url.extension()},
					{ title: "Localisation", field: "url", vertAlign: 'middle'},
					{ title: "Description", field: "description", editor: "input", mutator: value => value || '',  hozAlign: 'center'},
					{ title: "Taille du fichier", field: "size", formatter: cell => formatBytes(cell.getValue()), vertAlign: 'middle', hozAlign: 'center'},
					{ title: "Type", field: "type", hozAlign: 'center'},
					{ title: "Utilisations", field: "usages", hozAlign: 'center'},	
					{ title: "Propriétaire", field: "uploader", editor: "list", editorParams: {values:users}, hozAlign: 'center'},
					{ title: "Date d'ajout", field: "created_at", hozAlign: 'center'},
					{ title: "Date de modification", field: "updated_at", hozAlign: 'center'},
					{ title: "Accessible", field: "status", mutator: value => !!value, formatter: "tickCross", hozAlign: 'center'},
				]})
			
			function actionSwitch(e, cell) {
				const data = cell.getRow().getData()
				const icon = e.target

				if (icon.is('visualize.view')) {
					viewFile(data.url, data.type)
					
				} else if (icon.is('delete')) {
					const overlay = Overlay('deletion')
					const captionTag = overlay.section('header').child('span')
					const wrap = overlay.section('use-warning')
					const listTag = wrap.child('ul')
					
					if (data.status) {
						captionTag.innerText = 'Confirmer la suppression du fichier du serveur ?';
						listTag.innerHTML = '';
						overlay.section('delete-mode-wrap').show()
						
						references = data.usages || '';
						if (references) wrap.show()

						references.split(',').forEach(item => {if (item !== '') listTag.innerHTML += `<li>${item}</li>`})
					} else {
						captionTag.innerText = "Confirmer la suppression de l'entrée de ce fichier dans la base de données ?";
						wrap.hide()
						overlay.section('delete-mode-wrap').hide()
					}

					pendingAction.function = deleter;
					pendingAction.link = {'status': data.status, 'url': data.url};
					overlay.toggle()
				
				} else if (icon.is('download')) {
					downloadFile(data)
				}
			}
			
			filesTable.on('tableBuilt', statusWizard)
			filesTable.on('pageLoaded', statusWizard)

			return sourcedata
			}

// Categories table
async function fetchCategories(target) {
			const sourcedata = await dataFetcher('categories');
			
			categoriesTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataStretch",
				rowHeight: 36,
				columns: [
					{ title: "", field: "actions", mutator: value => (value || false) && true, formatter: cell => cell.getRow().getData().removable ? 
									`<span>
										<div class='icon ${cell.getValue() ? 'remove':'delete'} inline dynamic hoverable' title="Supprimer la catégorie"></div>
									</span>` : null, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', validator: ['unique', 'required'], editable: cell => cell.getRow().getData().removable},
					{ title: "Nom", field: "name", editor: "input", mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], editable: cell => cell.getRow().getData().removable, cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
					{ title: "Section", field: "section", editable: cell => cell.getRow().getData().actions, editor: "list", editorParams: {values: ['documents', 'ressources']}, validator: 'required'},
					{ title: "Nombre de fichiers", field: "files", mutator: value => value || 0},
				  ],
				})
			
			return sourcedata
			}

// Users table
async function fetchUsers(target) {
			const sourcedata = await dataFetcher('users');

			usersTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataTable",
				  rowHeight: 36,
				  columns: [
					{ title: "Role", field: "role", editor: "list", editorParams: {values:["user","admin"]}, editable: selfIntegrity, resizable: false},
					{ title: "Username", field: "username", editor:"input", validator: ['unique', 'required'], editable: selfIntegrity},
				  ],
				})
			
			function selfIntegrity(cell) {
				const rowData = cell.getRow().getData()
				return (!rowData.active && rowData.username !== 'admin')
				}
			
			return sourcedata
			}

// Tags table
async function fetchTags(target) {
			const sourcedata = await dataFetcher('tags');
			
			tagsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataStretch",
				rowHeight: 36,
				columns: [
					{ title: "", field: "actions", mutator: value => (value || false) && true, formatter: cell =>
							`<span>
								<div class='icon ${cell.getValue() ? 'remove':'delete'} inline dynamic hoverable' title="Supprimer l'étiquette"></div>
							</span>`, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
				  ],
				})
			
			return sourcedata
			}
	
// Tags table
async function fetchTypes(target) {
			const sourcedata = await dataFetcher('types');
			
			let colorsList = {}
			let colorCount = 0

			typesTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataStretch",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: value => (value || false) && true, formatter: cell => cell.getRow().getData().slug !== 'undefined' ?
								 `<span>
									<div class='icon ${cell.getValue() ? 'remove':'delete'} dynamic hoverable inline ' title="Supprimer la catégorie"></div>
								</span>` : null, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', editable: cell => cell.getRow().getData().slug !== 'undefined', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", editable: cell => cell.getRow().getData().slug !== 'undefined', mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
					{ title: "Couleur", field: "color", formatter: function(cell) {
																				// remove broken bonds
																				Object.entries(colorsList).forEach(([k, v]) => {if (!v.getElement()) delete colorsList[k]})
																				
																				// add new bond
																				colorsList[colorCount] = cell;
																				
																				// create input
																				input = document.createElement('input')
																				input.value = cell.getValue()
																				input.dataset.id = colorCount
																				input.setAttribute('type', 'color')

																				// handle cell change
																				input.onchange = e => {
																					tag = e.target
																					cell = colorsList[tag.dataset.id]
																					
																					if (tag.value.match(/#[a-f0-9]{6,8}/)) {
																						delete colorsList[tag.dataset.id]
																						cell.setValue(tag.value)
																					}
																				}

																				colorCount++;
																				return input}, editor: () => false, cellEdited: cell => autoEditedCells[cell.getRow().getData().id] = cell},
				  ],
				})
			
			return sourcedata
			}

// Articles table
async function fetchArticles(target) {
			const sourcedata = await dataFetcher('articles');
			const files = await dataFetcher('files', {'filetype':'image'}, 'files').then(response => response.reduce((list, file) => {list[file.url] = file.name; return list}, {}))

			// Extract authors list
			const authors = sourcedata.reduce((list, article) => {list.push(article.author); return list}, []).filterUnique()
			
			articlesTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataFill",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: (value, data) => data.url, formatter: function (cell) {
							const status = cell.getRow().getData().status
							const published = status === 'published'
							const archived  = status === 'archived'
							
							return `<span>
										<div class='icon modify redirect ${!published ? 'edit dynamic hoverable':''} inline' data-redirection="edit" title="Modifier l'article"></div>
										<div class='icon delete inline dynamic hoverable' title="Supprimer l'article"></div>
										<div class='icon visualize redirect ${published ? 'view': archived ? 'preview':'draft'} inline dynamic hoverable' data-redirection="${published ? 'view': archived ? 'preview':'draft'}" title="Voir l'article"></div>
										<div class='icon publish ${published ? 'archive': archived ? 'unarchive':'paper'} inline dynamic hoverable' data-publication="${published ? 'archive': archived ? 'unarchive':'paper'}" title="${published ? "Archiver l'article": archived ? "Désarchiver l'article":"Publier l'article"}"></div>
									</span>`}, cellClick: (e, cell) => actionSwitch(e, cell), resizable: false},
					{ title: "Statut", field: "status", formatter: function(cell) {cell.getElement().classList.toggle(cell.getValue()); return statusMatch[cell.getValue()]}, mutateLink: "actions", vertAlign: 'middle'},
					{ title: "URL", field: "url", editor:"input", validator: ['unique', 'required']},
					{ title: "Titre", field: "title", editor:"input", validator: "required"},
					{ title: "Auteur", field: "author", mutator: (value, d, p, t, cell) => value || cell.getInitialValue(), editor:"list", editorParams: {autocomplete: true, values: authors, freetext: true, allowEmpty: false, listOnEmpty: true}},
					{ title: "Date", field: "date", editor:"date", validator: "required", resizable: false},
					{ title: "Date (fin)", field: "end_date", mutator: value => value || null, editor:"date", resizable: false},
					{ title: "Type", field: "type", mutator: (value, d, p, t, cell) => value !== '' ? value : Object.keys(typesList).includes(cell.getInitialValue().toString()) ? cell.getInitialValue() : 0, formatter: cell => typesList[cell.getValue()]?.name || typesList[0].name, editor:"list", editorParams: cell => ({values:Object.keys(typesList).reduce((list, key) => {if (key != 0) list[key] = typesList[key].name; return list}, {})})},
					{ title: "Étiquettes", field: "tags", formatter: cell => cell.getValue()?.split(',').map(tag => tagsList[tag].name).join(', ') || null},
					{ title: "Image", field: "picture_path", mutator: value => value || '/files/placeholder.png', formatter: cell => cell.getValue()?.filename(true), editor:"list", editorParams: {values: files, emptyValue: '/files/placeholder.png', defaultValue: '/files/placeholder.png'}, widthShrink:1},
					],
				})
			
			function actionSwitch(e, cell) {
				const icon = e.target
				const value = cell.getValue()
				
				if (icon.is('publish')) {
					archiveArticle(e, cell, icon.dataset.publication === 'archive')
				
				} else if (icon.is('redirect.hoverable')) {
					const templates = {
						'preview': '/admin/tools/preview/article/',
						'draft': '/admin/tools/draft/article/',
						'edit':  '/admin/tools/edit/article/',
						'view':  '/articles/'
						}
					const url = templates[icon.dataset.redirection] + value

					if (!activeTable.integrity) {
						pendingAction.function = redirect
						pendingAction.link = url
						Overlay('validate').toggle()
					} else {
						redirect(url)
					}
				
				} else if (icon.is('delete')) {
						pendingAction.function = deleter
						pendingAction.link = [e, cell]
						Overlay('auth').toggle()
				}
			}

			return sourcedata
			}

// Logs table
async function fetchLogs(target) {
			const sourcedata = await dataFetcher('logs');
			
			logsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				rowHeight: 36,
				columns: [
					{ title: "Time", field: "time", resizable: false},
					{ title: "User", field: "user"},
					{ title: "Type", field: "section"},
					{ title: "Title", field: "message"},
					{ title: "Message", field: "content"},
					{ title: "IP", field: "ip", resizable: false},
					{ title: "Localization", field: "localization", resizable: false},
					],
				})
            
            return sourcedata
			}

// Logs table
async function fetchMails(target) {
			const sourcedata = await dataFetcher('mails');
			
			const types = {'sent': 'Envoyé', 'received': 'Reçu'}

			mailsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				rowHeight: 36,
				columns: [
					{ title: "", field: "actions", mutator: (value, data) => data.url, formatter: cell => 
							`<span>
									<div class='icon view inline visualize dynamic hoverable' 'title="Consulter le mail"></div>
							</span>`, cellClick: (e, cell) => actionSwitch(e, cell), resizable: false, frozen: true},
					{ title: "ID", field: "id", resizable: false},
					{ title: "Date", field: "time", resizable: false},
					{ title: "Sens", field: "type", visible: false, formatter: cell => types[cell.getValue()], resizable: false},
					{ title: "Objet", field: "subject"},
					{ title: "Expéditeurice", field: "sender", mutator: value => arrayFlatter(JSON.parse(value))},
					{ title: "Destinataire·s", field: "recipients", mutator: value => arrayFlatter(JSON.parse(value))},
					{ title: "Copies", field: "cc", visible: false, mutator: value => arrayFlatter(JSON.parse(value))},
					{ title: "Corps", field: "content", visible: false},
					{ title: "Pièces jointes", field: "attachments", formatter: cell => arrayFlatter(JSON.parse(cell.getValue()), 'filename')},
					{ title: "Lié à", field: "link"},
					],
				})
            
			function actionSwitch(e, cell) {
				const data = cell.getRow().getData()
				const icon = e.target

				if (icon.is('visualize.view')) {
					viewMail(data)
				}
			}

            return sourcedata
			}

// HelloAsso main table
async function fetchHA(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/all').then(response => response.body)
			
			// forms match table - key is form slug in ordersList
			const haFormsList = sourcedata.formsList.reduce((list, item) => {
				list[item.formSlug] = {'type': haTypeMatch[item.formType], 'url': item.url, 'name': item.title}
				return list
			}, {})
			
			haTable = new Tabulator(target, {
				data: sourcedata.ordersList,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				columns: [
				{ title: "", field: "receipts",  mutator: (value, data) => data.payments?.[0].paymentReceiptUrl || null,
												 formatter: function (cell) {
																			let content = cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline dynamic' title="Reçu du paiement"></a>`:'';
																			cell.getRow().getData().items.forEach(item => content += item.ticketUrl !== null ? `<a href="${item.ticketUrl}" target="_blank_" class='icon ticket inline dynamic' title="Billet"></a>`:'')

																			return '<span>' + content + '</span>'
																			}, resizable: false, frozen: true
				},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID", mutator: (value, data) => data.payments?.[0].id || null, resizable: false},
				{ title: "Date", field: "date", formatter: cell => cellFormatter(cell, 'date'), resizable: false},
				{ title: "Mail", field: "payer.email"},
				{ title: "Identité", field: "fullname", mutator: (value, data) => data.payer.firstName + ' ' + data.payer.lastName.toUpperCase(), headerFilter: true},
				{ title: "Formulaire", field: "formName", mutator: (value, data) => haFormsList[data.formSlug].name, headerFilter:'list', headerFilterParams: () => ({'values': Object.keys(haFormsList).reduce((list, item) => [...list, haFormsList[item].name], [])})},
				{ title: "Montant", field: "amount.total", width: 100, mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Items", field: "itemslist", mutator: function (value, data) {
																						const receipts = data.items.reduce((list, item) => {list[item.name] = (list[item.name] ?? 0) + 1; return list}, {})
																						const text = Object.keys(receipts).reduce((list, key) => [...list, `${key} (${receipts[key]})`], []).join(', ')
																						
																						return text
																						}, formatter: cell => `<small>${cell.getValue()}</small>`
				},
			]})
            
			// Fix column width to fit max number of icons in receipts field
			haTable.on("pageLoaded", () => haTable.redraw())

			return sourcedata
			}

// HelloAsso Goodies table
async function fetchGoodies(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/goodies').then(response => response.body)
			
			goodiesTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200],
				paginationCounter:"rows",
				columns: [
				{ title: "", field: "delivered", editor: () => false, editable: cell => !cell.getInitialValue(), formatter: "tickCross", cellClick: (e, cell) => !cell.getInitialValue() ? cell.setValue(!cell.getValue()):null, resizable: false, frozen: true, vertAlign: "middle", hozAlign: "center"},
				{ title: "", field: "receipts", formatter: cell => cell.getValue() !== null ?
														`<span>
															<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline dynamic' title="Reçu du paiement"></a>
														</span>` : null, resizable: false, frozen: true},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: cell => cellFormatter(cell, 'date'), resizable: false},
				{ title: "Mail", field: "mail"},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Item", field: "item", headerFilter: 'list', headerFilter:'list', headerFilterParams: {'values': goodiesList}},
				{ title: "Enregistré sur Paheko ?", field: "registered", editor: () => false, editable: cell => !cell.getInitialValue(), formatter: "tickCross", cellClick: (e, cell) => !cell.getInitialValue() ? cell.setValue(!cell.getValue()):null, resizable: false, vertAlign: "middle", hozAlign: "center"},
				]})
			
			return sourcedata
			}

// DQ7 Table
async function fetchDQ7(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/drama-queer-7').then(response => response.body)

			dq7Table = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200],
				paginationCounter:"rows",
				columns: [
				{title: "", field: "receipts", formatter: cell => cell.getValue() !== null ?
															`<span>
																<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline dynamic' title="Reçu du paiement"></a>
															</span>` : null, resizable: false},
				{title: "", field: "ticket", formatter: cell => cell.getValue() !== null ?
															`<span>
																<a href="${cell.getValue()}" target="_blank_" class='icon ticket inline dynamic' title="Billet"></a>
															</span>` : null, resizable: false, frozen: true},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: cell => cellFormatter(cell, 'date'), resizable: false},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Tier", field: "item", headerFilter:'list', headerFilterParams: {'values': pricesList}},
				{ title: "Étudiant·e ?", field: "student", headerFilter:'list', headerFilterParams: {'values': ['Oui','Non']}, hozAlign: 'center'}
				]})
			
			return sourcedata
			}

// DQ7 Table
async function fetchDQ8(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/drama-queer-8').then(response => response.body)

			dq8Table = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200],
				paginationCounter:"rows",
				columns: [
				{title: "", field: "receipts", formatter: cell => cell.getValue() !== null ?
															`<span>
																<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline dynamic' title="Reçu du paiement"></a>
															</span>` : null, resizable: false},
				{title: "", field: "ticket", formatter: cell => cell.getValue() !== null ?
															`<span>
																<a href="${cell.getValue()}" target="_blank_" class='icon ticket inline dynamic' title="Billet"></a>
															</span>` : null, resizable: false, frozen: true},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: cell => cellFormatter(cell, 'date'), resizable: false},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Tier", field: "item", headerFilter:'list', headerFilterParams: {'values': pricesList}},
				{ title: "Étudiant·e ?", field: "student", headerFilter:'list', headerFilterParams: {'values': ['Oui','Non']}, hozAlign: 'center'}
				]})
			
			return sourcedata
			}

// Paheko categories table
async function fetchGroups(target) {
			const sourcedata = await fetchAPI('/api/paheko/get/composition').then(response => response.body)
			const categories = await fetchAPI('/api/paheko/get/categories').then(response => response.body)
			const holder = Input('[value="categories"]').nextElementSibling.nextElementSibling
			
			// Generate dynamically subsection buttons
			holder.innerHTML = `
					<span>
						<input class='checkbox' type='radio' id="cat-all" name="category" value="all" checked>
						<label class='tag-item option orange' for="cat-all">Tout</label>
					</span>`;
			Object.values(categories).forEach(cat => {
					const span = document.createElement('span')

					span.innerHTML = `
						<input class='checkbox' type='radio' id="cat-${cat.id}" name="category" value="${cat.name}">
						<label class='tag-item option orange' for="cat-${cat.id}">${cat.name}</label>
						`
					holder.append(span)
			})

			// Tables subsection buttons management
			holder.inputs().forEach(item => item.onchange = e => {
					if (e.target.value === 'all') {
						groupsTable.clearFilter()
						groupsTable.setSort([{column:"id", dir:"asc"}])
					} else {
						groupsTable.setFilter("category", "=", e.target.value)
						groupsTable.setSort([{column:"nom", dir:"asc"}])
					}
			})
			
			// create tabulator
			groupsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				initialSort:[
					{column:"id", dir:"asc"}
				],
				rowHeight: 36,
				columns: [
					{ title: "ID", field: "id", resizable: false, sorter: 'number'},
					{ title: "Catégorie", field: "category", resizable: false},
					{ title: "Nom", field: "nom", headerFilter: true},
					{ title: "Mail", field: "email"},
					{ title: "Première adhésion", field: "date_inscription", mutator: value => value?.split(' ')[0] || null}
					],
				});
			
			return sourcedata
			}

// Subscriptions table
async function fetchSubscriptions(target) {
			const sourcedata = await fetchAPI('/api/paheko/get/users').then(response => response.body)
			const services   = await fetchAPI('/api/paheko/get/services').then(response => response.body)
			const holder = Input('[value="subscriptions"]').nextElementSibling.nextElementSibling

			// Generate dynamically subsection buttons
			holder.innerHTML = '';
			Object.values(services).forEach(serv => {
					const span = document.createElement('span')
					const years = serv.label.match(/[0-9]{2}\/[0-9]{2}/)[0]

					span.innerHTML = `
						<input class='checkbox' type='radio' id="subc-${serv.id}" name="subscription" value="${serv.label}">
						<label class='tag-item option orange' for="subc-${serv.id}">20${years.split('/')[0]}-20${years.split('/')[1]}</label>
						`

					span.input().onchange = e => {
						subscriptionsTable.setFilter("label", "=", e.target.value)
						subscriptionsTable.setSort([{column:"nom", dir:"asc"}])
					}
					holder.append(span)
			})
			holder.children[1].children[0].checked = true
			
			// create tabulator
			subscriptionsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				initialSort:[
					{column:"id", dir:"asc"}
				],
				initialFilter:[
					{field:"label", type:"=", value:services[1].label}
				],
				rowHeight: 36,
				columns: [
					{ title: "ID", field: "id", resizable: false, sorter: 'number'},
					{ title: "Nom", field: "nom", headerFilter: true},
					{ title: "Mail", field: "email"},
					{ title: "Date d'adhésion", field: "date", resizable: false},
					{ title: "Expiration", field: "expiry_date", resizable: false}
					],
				})
			
			return sourcedata
			}

async function fetchStocks(target) {
			const sourcedata = await fetchAPI('/api/paheko/get/goodies').then(response => response.body)
			
			// create tabulator
			stocksTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				rowHeight: 36,
				columns: [
					{ title: "Nom", field: "name", resizable: false},
					{ title: "Prix d'achat", field: "purchase_price", mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
					{ title: "Prix de vente", field: "price", mutator: value => value/100, formatter: cell => cellFormatter(cell, 'price'), hozAlign: "center"},
					{ title: "Stock", field: "stock", sorter: 'number'},
					{ title: "Description", field: "description", resizable: false}
					],
				})
			
			return sourcedata
			}

//// SHARED FUNCTIONS
function slugUniqueness(cell, value) {
		const slug = cell.getRow().getData().slug
		const data = Array.from(activeTable.table.getColumn('slug').getCells()).map(cell => cell.getValue()).filter(value => value !== slug)
		
		return !data.includes(value?.urlForm())
		}

function cellFormatter(cell, type) {
		if (type === 'price') {
			return cell.getValue().toString().replace('.',',') + ' €'
		
		} else if (type === 'date') {
			let body = cell.getValue().split('+')[0]
			return body.split('T')[0].split('-').reverse().join('/') + ' ' + body.split('T')[1]
		}
}

function arrayFlatter(array, field = null) {
		return Array.from(Object.values(array ?? [])).reduce((list, item) => [...list, field ? item[field] : item], []).join(', ')
}

//// UTILS FUNCTIONS
// Fetch SQL data
async function dataFetcher(type, extra=null, engine='database') {
            const queryData = {'action':'fetch', 'type':type, 'csrf':csrfToken}
		    
            if (extra) {// extra query arguments
                Object.keys(extra).forEach(key => queryData[key] = extra[key])
            }

            const jsonData = JSON.stringify(queryData)
		    const data = await fetchAPI(`/admin/engine/${engine}`, jsonData).then(response => response.body)

		    return data
		}

// Sync viewer tables
async function viewerSync(e) {
		const icon = e.target
		icon.show()
		icon.setAttribute('title', `Synchronisation des ${icon.dataset.type}`)
		notify("Synchronisation en cours", `Récupération des ${icon.dataset.type} depuis la base de données.`, 'orange')
		await tablesBuilder()
		notify("Synchronisation terminée", `Les ${icon.dataset.type} ont correctement été synchronisés.`, 'green')
		icon.setAttribute('title', `Synchroniser les ${icon.dataset.type}`)
		icon.hide()
		}
		
async function saveCurrentData() {
			const table = activeTable.table
			const type = activeTable.type

			// select edited rows, with values different from original
			const modifiedRows = Array.from(table.getEditedCells().concat(Object.values(autoEditedCells))).map(c => c.getValue() !== c.getInitialValue() ? c.getRow().getData():null).filterUnique();

			// update fetch
			const jsonData = JSON.stringify({'action':'update', 'type':type, 'csrf':csrfToken, 'data':modifiedRows})
			const response = await fetchAPI('/admin/engine/database', jsonData)

			if (!response.ok) {
				notify("Échec de la mise à jour", `Des erreurs sont survenues lors de la mise à jour des données de la table '${type}'. Consultez les logs pour plus d'informations.`, 'red')
				return
			} else {
				Block('main').section('data-actions').hide()
				
				// Make new rows non-removable
				if (activeTable.appendable) activeTable.table.getColumn('actions').getCells().forEach(cell => cell.setValue(false))
				
				// Reset table source to current data
				resetTabledata(activeTable, true)

				// Update config Objects
				if (['tags', 'types'].includes(type)) {
					if (type === 'tags') {
						tableMatch.tags.table.getData().forEach(row => tagsList[row.id] = {'slug':row.slug, 'name':row.name})
					} else if (type === 'types') {
						tableMatch.types.table.getData().forEach(row => typesList[row.id] = {'slug':row.slug, 'name':row.name, 'color':row.color})
					}

					// Update data in articles table
					tableMatch.articles.table.getRows().forEach(row => row.reformat())
				}

				notify("Succès", `Les données de la table '${activeTable.type}' ont été mises à jour.`, 'green')
			}
	}

// restore table data
async function resetTabledata(table = activeTable, force = false, source = null) {
			if (force) tableMatch[table.type].source = JSON.stringify(source ?? table.table.getData())
			
			table.table.setData(tableMatch[table.type].source).then(() => {
						dataChecker(table.table.getData())
						table.table.clearCellEdited()
						table.node.querySelectorAll('.tabulator-cell[changed]').forEach(elem => elem.unset('changed'))
					})
	    }

// navigate between tables
function toggleTable(query) {
	        Object.values(tableMatch).forEach(table => table.node.id === query ? table.node.show():table.node.hide())

			tableMatch[query]?.appendable ? Button('add-row')?.show() : Button('add-row')?.hide()
		}