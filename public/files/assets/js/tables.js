//// TABLES POINTERS
let activeTable;
let pendingAction = {};
let tableMatch;
let autoEditedCells = {};

let usersTable;
let articlesTable;
let tagsTable;
let typesTable;
let filesTable;
let categoriesTable;
let haTable;
let goodiesTable;
let dq7Table;
let groupsTable;
let subscriptionsTable;
let stocksTable;

//// Utils
function tablesListener(watcher=false, selector='.input-container') {
		tablesSwitcher()

		// Tables buttons management
		Array.from(document.querySelector(selector).children).forEach(
				item => {
					item.addEventListener('change', e => {
						tkn = e.target.findParent('span').child('input').value;
						toggleTable(`#${tkn}`);
						activeTable=tableMatch[tkn];

						if (watcher) dataChecker(activeTable.table.getData());
					})
				})
}

function tablesSwitcher() {
		Object.values(tableMatch).forEach(value => value.table.on("tableBuilt", () => value.source = JSON.stringify(value.table.getData())))
		Object.values(tableMatch).forEach(value => value.table.on("dataChanged", data => dataChecker(data)))
		Object.values(tableMatch).forEach(value => value.table.on("cellEdited", cell => cell.getValue() !== cell.getInitialValue() ? cell.getElement().set('changed'):cell.getElement().unset('changed')))
}

function dataChecker(data) {
			main = document.querySelector('#main')

			if (JSON.stringify(data) === activeTable.source) {
				main.child('.data-actions').hide()
				activeTable.integrity = true;
			} else if (activeTable.table.validate() !== true) {
				main.child('.data-actions').hide()
				activeTable.integrity = false;
			} else {
				main.child('.data-actions').show()
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
					{ title: "", field: "actions", mutator: function (value, data) {return data.url}, formatter: function (cell) {
							rowdata = cell.getRow().getData();
							v_type = rowdata.type.match(/(application\/pdf|image\/.*)/)
							return `<span>
										<div class='icon view inline medium ${rowdata.status && v_type ? 'visualize dynamic hoverable':''}' ${rowdata.status && v_type ? 'title="Voir le fichier"':''}>
											<span class='img'></span>
										</div>
										<div class='icon delete inline medium dynamic hoverable' title="Supprimer ${rowdata.status ? 'le fichier':"l'entrée"}">
											<span class='img'></span>
										</div>
										<div class='icon inline medium ${rowdata.status ? 'download dynamic hoverable':''}' title="Télécharger le fichier">
											<span class='img'></span>
										</div>
									</span>`}, cellClick: (e, cell) => actionSwitch(e, cell), resizable: false, frozen: true},
					{ title: "Nom du fichier", field: "name", mutator: value => value.filename(), formatter: cell => cell.getValue() + '.' + cell.getRow().getData().extension, editor: 'input', validator: 'unique', vertAlign: 'middle'},
					{ title: "Extension", field: "extension", visible: false, mutator: (value, data) => data.url.extension()},
					{ title: "Localisation", field: "url", vertAlign: 'middle'},
					{ title: "Description", field: "description", editor: "input", mutator: value => value || '',  hozAlign: 'center'},
					{ title: "Taille du fichier", field: "size", formatter: cell => formatBytes(cell.getValue()), vertAlign: 'middle', hozAlign: 'center'},
					{ title: "Type", field: "type", hozAlign: 'center'},
					{ title: "Utilisations", field: "usages", hozAlign: 'center'},	
					{ title: "Propriétaire", field: "uploader", editor: "list", editorParams: {values:users}, hozAlign: 'center'},
					{ title: "Date d'ajout", field: "created_at", hozAlign: 'center'},
					{ title: "Date de modification", field: "updated_at", hozAlign: 'center'},
					{ title: "Accessible", field: "status", mutator: value => value === 1 ? true:false, formatter: "tickCross", hozAlign: 'center'},
				]});
			
			function actionSwitch(e, cell) {
				const data = cell.getRow().getData()

				if (e.target.findParent('.icon.visualize.view').nodeName !== 'BODY') {
					viewFile(data.url)
				
				} else if (e.target.findParent('.icon.delete').nodeName !== 'BODY') {
					overlay = Overlay('deletion')
					captionTag = overlay.querySelector('.header').child('span')
					wrap = overlay.querySelector('.use-warning')
					listTag = wrap.querySelector('ul')
					
					if (data.status) {
						captionTag.innerText = 'Confirmer la suppression du fichier du serveur ?';
						listTag.innerHTML = '';
						overlay.querySelector('.delete-mode-wrap').show();
						
						references = data['used_by'] ? data['used_by']:'';
						references !== '' ? wrap.show():null;

						references.split(',').forEach(item => {
										if (item !== '') {
											listTag.innerHTML += `<li>${item}</li>`
										}
						})
					} else {
						captionTag.innerText = "Confirmer la suppression de l'entrée de ce fichier dans la base de données ?";
						wrap.hide()
						overlay.querySelector('.delete-mode-wrap').hide()
					}

					pendingAction.function = deleter;
					pendingAction.link = {'status': data.status, 'url': data.url};
					overlay.toggle()
				
				} else if (e.target.findParent('.icon.download').nodeName !== 'BODY') {
					data = cell.getRow().getData()
					downloadFile(data)
				}
			}
			
			filesTable.on('tableBuilt', () => statusWizard())
			filesTable.on('pageLoaded', () => statusWizard())

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
					{ title: "", field: "actions", mutator: (value, data) => data.slug, formatter: cell => cell.getRow().getData().removable ? 
									`<span>
										<div class='icon ${cell.getRow().getData().new ? 'remove':'delete'} inline medium dynamic hoverable' title="Supprimer la catégorie">
											<span class='img'></span>
										</div>
									</span>` : null, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', validator: ['unique', 'required'], editable: cell => cell.getRow().getData().removable, cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
					{ title: "Nom", field: "name", editor: "input", mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], editable: cell => cell.getRow().getData().removable},
					{ title: "Section", field: "section", editable: cell => cell.getRow().getData().new, editor: "list", editorParams: {values: ['documents', 'ressources']}, validator: 'required'},
					{ title: "Nombre de fichiers", field: "files", mutator: value => value || 0},
				  ],
				});
			
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
				});
			
			function selfIntegrity(cell) {
				const rowData = cell.getRow().getData();
				return (!rowData.active && rowData.username !== 'admin');
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
					{ title: "", field: "actions", mutator: (value, data) =>  data.slug, formatter: cell =>
							`<span>
								<div class='icon ${cell.getRow().getData().new ? 'remove':'delete'} inline medium dynamic hoverable' title="Supprimer l'étiquette">
									<span class='img'></span>
								</div>
							</span>`, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
				  ],
				});
			
			return sourcedata
			}
	
// Tags table
async function fetchTypes(target) {
			const sourcedata = await dataFetcher('types');
			
			let colorMatchTable = {}
			let colorCount = 0;

			typesTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataStretch",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: (value, data) => data.slug, formatter: cell => cell.getRow().getData().slug !== 'undefined' ?
								 `<span>
									<div class='icon ${cell.getRow().getData().new ? 'remove':'delete'} dynamic hoverable inline medium ' title="Supprimer la catégorie">
										<span class='img'></span>
									</div>
								</span>` : null, cellClick: (e, cell) => deleterTrigger(e, cell), resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', editable: cell => cell.getRow().getData().slug !== 'undefined', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", editable: cell => cell.getRow().getData().slug !== 'undefined', mutator: (value, data) => value?.trim(), validator: [slugUniqueness, 'required'], cellEdited: cell => cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())},
					{ title: "Couleur", field: "color", formatter: function(cell) {
																				// remove broken bonds
																				Object.entries(colorMatchTable).forEach(([k, v]) => {if (!v.getElement()) delete colorMatchTable[k]})
																				
																				// add new bond
																				colorMatchTable[colorCount] = cell;
																				
																				// create input
																				input = document.createElement('input')
																				input.value = cell.getValue()
																				input.dataset.id = colorCount
																				input.setAttribute('type', 'color')

																				// handle cell change
																				input.onchange = e => {
																					tag = e.target
																					cell = colorMatchTable[tag.dataset.id]
																					
																					if (tag.value.match(/#[a-f0-9]{6,8}/)) {
																						delete colorMatchTable[tag.dataset.id]
																						cell.setValue(tag.value)
																					}
																				}

																				colorCount++;
																				return input}, editor: () => false, cellEdited: cell => autoEditedCells[cell.getRow().getData().id] = cell},
				  ],
				});
			
			return sourcedata
			}

// Articles table
async function fetchArticles(target) {
			const sourcedata = await dataFetcher('articles');
			const files = await dataFetcher('files', {'filetype':'image'}, 'files').then(response => response.reduce((list, file) => {list[file.url] = file.name; return list}, {}))

			const statusMatch = {'published': 'Publié', 'archived': 'Archivé', 'draft': 'Brouillon'}

			// Extract authors list
			const authors = sourcedata.reduce((list, article) => {list.push(article.author); return list}, []).filterUnique()
			
			articlesTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataFill",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: (value, data) => data.url, formatter: function (cell) {
							const value = cell.getRow().getData().status;
							return `<span>
										<div class='icon modify ${value !== 'published' ? 'edit dynamic hoverable':''} inline medium' title="Modifier l'article">
											<span class='img'></span>
										</div>
										<div class='icon delete inline medium dynamic hoverable' title="Supprimer l'article">
											<span class='img'></span>
										</div>
										<div class='icon visualize ${value === 'published' ? 'view':(value === 'archived' ? 'preview':'draft')} inline medium dynamic hoverable' title="Voir l'article">
											<span class='img'></span>
										</div>
										<div class='icon ${value === 'published' ? 'archive':(value === 'archived' ? 'unarchive':'paper')} inline medium dynamic hoverable' title="${value === 'published' ? "Archiver l'article":(value === 'archived' ? "Désarchiver l'article":"Publier l'article")}">
											<span class='img'></span>
										</div>`}, cellClick: (e, cell) => actionSwitch(e, cell), resizable: false},
					{ title: "Statut", field: "status", formatter: function(cell) {cell.getElement().classList.toggle(cell.getValue()); return statusMatch[cell.getValue()]}, vertAlign: 'middle'},
					{ title: "URL", field: "url", editor:"input", validator: ['unique', 'required']},
					{ title: "Titre", field: "title", editor:"input", validator: "required"},
					{ title: "Auteur", field: "author", mutator: (value, d, p, t, cell) => value || cell.getInitialValue(), editor:"list", editorParams: {autocomplete: true, values: authors, freetext: true, allowEmpty: false, listOnEmpty: true}},
					{ title: "Date", field: "date", editor:"date", validator: "required", resizable: false},
					{ title: "Date (fin)", field: "end_date", mutator: value => value || null, editor:"date", resizable: false},
					{ title: "Type", field: "type", mutator: (value, d, p, t, cell) => parseInt(value || cell.getInitialValue() || 0), formatter: function(cell) {return cell.getValue() === 0 ? 'Indéfini' : typesCounter[cell.getValue()]}, editor:"list", editorParams: {values:typesCounter}},
					{ title: "Étiquettes", field: "tags", mutator: value => value?.split(','), formatter: cell => cell.getValue()?.map(tag => tagsMatchTable[tag].name).join(', ')},
					{ title: "Image", field: "picture_path", mutator: value => value || '/files/placeholder.png', formatter: cell => cell.getValue()?.filename(true), editor:"list", editorParams: {values: files, emptyValue: '/files/placeholder.png', defaultValue: '/files/placeholder.png'}, widthShrink:1},
					],
				});
			
			function actionSwitch(e, cell) {
				if (e.target.findParent('.icon.archive').nodeName !== 'BODY') {
					archiveArticle(e, cell, true)
				} else if (e.target.findParent('.icon.unarchive').nodeName !== 'BODY') {
					archiveArticle(e, cell, false)
				} else if (e.target.findParent('.icon.visualize.view').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/articles/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/articles/${cell.getValue()}`)
					}
				} else if (e.target.findParent('.icon.visualize.preview').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/admin/tools/preview/article/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/article/preview/${cell.getValue()}`)
					}
				} else if (e.target.findParent('.icon.visualize.draft').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/admin/tools/draft/article/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/draft/article/${cell.getValue()}`)
					}
				} else if (e.target.findParent('.icon.delete').nodeName !== 'BODY') {
						pendingAction.function = deleter;
						pendingAction.link = [e, cell];
						Overlay('auth').toggle()
				} else if (e.target.findParent('.icon.modify.edit').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/admin/tools/edit/article/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/edit/article/${cell.getValue()}`)
					}
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
					{ title: "IP", field: "ip", resizable: false},
					{ title: "Localization", field: "localization", resizable: false},
					{ title: "User", field: "user"},
					{ title: "Type", field: "section"},
					{ title: "Title", field: "message"},
					{ title: "Message", field: "content"}
					],
				});
            
            return sourcedata
			}

// HelloAsso main table
async function fetchHA(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/all').then(response => response.body)
			
			// event match table
			const matchTypeTable = {'Shop':'Boutique', 'Event':'Billeterie', 'Donation':'Don', 'Membership':'Adhésion'}
			
			// forms match table - key is form as it is what we find in ordersList
			const matchFormsTable = sourcedata.formsList.reduce((list, item) => {
				list[item.formSlug] = {'type': matchTypeTable[item.formType], 'url': item.url, 'name': item.title}
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
																			content = cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
																														<span class='img'></span>
																													</a>`:'';
																			cell.getRow().getData().items.forEach(item => {
																					if (item.ticketUrl !== null) {
																						content += `<a href="${item.ticketUrl}" target="_blank_" class='icon ticket inline medium dynamic' title="Billet">
																										<span class='img'></span>
																									</a>`;
																					}
																				})
																			return '<span>' + content + '</span>'
																			}, resizable: false, frozen: true
				},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID", mutator: (value, data) => data.payments?.[0].id || null, resizable: false},
				{ title: "Date", field: "date", formatter: cell => haFormatter(cell, 'date'), resizable: false},
				{ title: "Mail", field: "payer.email"},
				{ title: "Identité", field: "fullname", mutator: (value, data) => data.payer.firstName + ' ' + data.payer.lastName.toUpperCase(), headerFilter: true},
				/*{ title: "Type", field: "formType", mutator: function (value, data) {
																					return matchTypeTable[value]
																					},
				},*/
				{ title: "Formulaire", field: "formName", mutator: (value, data) => matchFormsTable[data.formSlug].name, headerFilter:'list', headerFilterParams: () => ({'values': Object.keys(matchFormsTable).reduce((list, item) => [...list, matchFormsTable[item].name], [])})
				},
				{ title: "Montant", field: "amount.total", width: 100, mutator: value => value/100, formatter: cell => haFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Items", field: "itemslist", mutator: function (value, data) {
																						receipts = data.items.reduce((list, item) => {list[item.name] = (list[item.name] ?? 0) + 1; return list}, {})
																						
																						text = Object.keys(receipts).reduce((list, key) => [...list, `${key} (${receipts[key]})`], []).join(', ')
																						
																						return text
																						}, formatter: cell => `<small>${cell.getValue()}</small>`
				},
			]});
            
			haTable.on("pageLoaded", () => haTable.redraw())

			return sourcedata
			}

// HelloAsso Goodies table
async function fetchGoodies(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/goodies').then(response => response.body)
			
			const itemsType = ["Pin's arcENSiel", "Pin's Mouflon", "Sticker", "Briquet", "Affiche"]

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
															<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
																<span class='img'></span>
															</a>
														</span>` : null, resizable: false, frozen: true},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: cell => haFormatter(cell, 'date'), resizable: false},
				{ title: "Mail", field: "mail"},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: value => value/100, formatter: cell => haFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Item", field: "item", headerFilter: 'list', headerFilter:'list', headerFilterParams: {'values': itemsType}},
				{ title: "Enregistré sur Paheko ?", field: "registered", editor: () => false, editable: cell => !cell.getInitialValue(), formatter: "tickCross", cellClick: (e, cell) => !cell.getInitialValue() ? cell.setValue(!cell.getValue()):null, resizable: false, vertAlign: "middle", hozAlign: "center"},
				]});
			
			return sourcedata
			}

// DQ7 Table
async function fetchDQ7(target) {
			const sourcedata = await fetchAPI('/api/helloasso/get/drama-queer-7').then(response => response.body)
			
			const itemsType = ["Prix conseillé", "Prix minimum", "Prix libre", "Paiement en espèces", "Liste d'attente"]

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
																<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
																	<span class='img'></span>
																</a>
															</span>` : null, resizable: false
				},
				{title: "", field: "ticket", formatter: cell => cell.getValue() !== null ?
															`<span>
																<a href="${cell.getValue()}" target="_blank_" class='icon ticket inline medium dynamic' title="Billet">
																	<span class='img'></span>
																</a>
															</span>` : null, resizable: false, frozen: true
				},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: cell => haFormatter(cell, 'date'), resizable: false},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: value => value/100, formatter: cell => haFormatter(cell, 'price'), hozAlign: "center"},
				{ title: "Tier", field: "item", headerFilter: 'list', headerFilter:'list', headerFilterParams: {'values': itemsType}},
				{ title: "Étudiant·e ?", field: "student", headerFilter: 'list', headerFilter:'list', headerFilterParams: {'values': ['Oui','Non']}, hozAlign: 'center'}
				]});
			
			return sourcedata
			}

// Paheko categories table
async function fetchGroups(target) {
			const sourcedata = await fetchAPI('/api/paheko/get/composition').then(response => response.body)
			const categories = await fetchAPI('/api/paheko/get/categories').then(response => response.body)
			
			// Generate dynamically subsection buttons
			let holder = document.querySelector('input[value="categories"] ~ .input-container.checkboxes.subsection')
			holder.innerHTML = `
				<span>
					<input class='checkbox' type='radio' id="cat-all" name="category" value="all" checked>
					<label class='tag-item option orange' for="cat-all">Tout</label>
				</span>`;
			Object.values(categories).forEach(cat => {
					span = document.createElement('span')

					span.innerHTML = `
						<input class='checkbox' type='radio' id="cat-${cat.id}" name="category" value="${cat.name}">
						<label class='tag-item option orange' for="cat-${cat.id}">${cat.name}</label>
						`
					holder.append(span)
			})

			// Tables subsection buttons management
			Array.from(document.querySelector('input[value="categories"] ~ .input-container.checkboxes.subsection').children).forEach(
					item => {
						item.addEventListener('change', e => {
							e.stopPropagation()
							tkn = e.target.findParent('span').child('input').value;
										
							if (tkn === 'all') {
									groupsTable.clearFilter()
									groupsTable.setSort([{column:"id", dir:"asc"}]);
							} else {
									groupsTable.setFilter("category", "=", tkn)
									groupsTable.setSort([{column:"nom", dir:"asc"}]);
									}
							})
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
			
			// Generate dynamically subsection buttons
			let holder = document.querySelector('input[value="subscriptions"] ~ .input-container.checkboxes.subsection')
			holder.innerHTML = '';
			Object.values(services).forEach(serv => {
					years = serv.label.match(/[0-9]{2}\/[0-9]{2}/)[0]

					span = document.createElement('span')

					span.innerHTML = `
						<input class='checkbox' type='radio' id="subc-${serv.id}" name="subscription" value="${serv.label}">
						<label class='tag-item option orange' for="subc-${serv.id}">20${years.split('/')[0]}-20${years.split('/')[1]}</label>
						`

					span.onchange = e => {
						e.stopPropagation()
						tkn = e.target.findParent('span').child('input').value;

						subscriptionsTable.setFilter("label", "=", tkn)
						subscriptionsTable.setSort([{column:"nom", dir:"asc"}]);
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
				});
			
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
					{ title: "Prix d'achat", field: "purchase_price", mutator: value => value/100, formatter: cell => haFormatter(cell, 'price'), hozAlign: "center"},
					{ title: "Prix de vente", field: "price", mutator: function (val) {return val/100}, formatter: cell => haFormatter(cell, 'price'), hozAlign: "center"},
					{ title: "Stock", field: "stock", sorter: 'number'},
					{ title: "Description", field: "description", resizable: false}
					],
				});
			
			return sourcedata
			}

//// SHARED FUNCTIONS
function slugUniqueness(cell, value) {
		thisVal = cell.getRow().getData().slug;
		
		// extract column values
		data = Array.from(activeTable.table.getColumn('slug').getCells()).map(c => {return c.getValue()}).filter(val => val !== thisVal);
		
		return !data.includes(value?.urlForm())
		}

function haFormatter(cell, type) {
		if (type === 'price') {
			return cell.getValue().toString().replace('.',',') + ' €'
		
		} else if (type === 'date') {
			let body = cell.getValue().split('+')[0]
			return body.split('T')[0].split('-').reverse().join('/') + ' ' + body.split('T')[1]
		}
}

//// UTILS FUNCTIONS
// Fetch SQL data
async function dataFetcher(type, extra=null, engine='database') {
            queryData = {'action':'fetch', 'type':type, 'csrf':csrfToken}
		    
            if (extra) {// extra query arguments
                Object.keys(extra).forEach(key => {queryData[key] = extra[key]})
            }

            jsonData = JSON.stringify(queryData)
		    data = await fetchAPI(`/admin/engine/${engine}`, jsonData).then(response => response.body)

		    return data
		}

// Restore table cells dom state
function resetState(table) {
			document.querySelectorAll(`${table.selector} .tabulator-cell[changed]`).forEach(elem => {
				elem.unset('changed')
				}
			)
	    }

// restore table data
async function resetTabledata(table = activeTable) {
			table.table.setData(table.source)
				.then(() => {
						dataChecker(table.table.getData());
						resetState(table)
					})
	    }

// navigate between tables
function toggleTable(query) {
	        document.querySelector(query).show();
	        document.querySelectorAll(`.table:not(${query})`).forEach(e => {e.hide()});
		}