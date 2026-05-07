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
let haTable;
let goodiesTable;
let dq7Table;
let categoriesTable;
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
		Object.values(tableMatch).forEach(value => value.table.on("tableBuilt", function(){value.source = JSON.stringify(value.table.getData())}))
		Object.values(tableMatch).forEach(value => value.table.on("dataChanged", function(data){dataChecker(data)}))
		Object.values(tableMatch).forEach(value => value.table.on("cellEdited", function(cell){cell.getValue() !== cell.getInitialValue() ? cell.getElement().setAttribute('changed',''):cell.getElement().removeAttribute('changed')}))
}

function dataChecker(data) {
			main = document.querySelector('#main')

			if (JSON.stringify(data) === activeTable.source) {
				main.child('.data-actions').hide()
				activeTable.integrity = true;
			} else {
				main.child('.data-actions').show()
				activeTable.integrity = false;
			}
		}

//// TABULATOR GENERATORS
// Files table
async function fetchFiles(target) {
			sourcedata = await dataFetcher('files').then(response => response['sql-data']);
			users = await dataFetcher('users').then(response => response['sql-data'].reduce((list, user) => {list[user.username] = user.username; return list}, {}));

			statusMatch = {'published': 'Publié', 'archived': 'Archivé', 'draft': 'Brouillon'}

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
										<div class='icon view inline medium ${rowdata.status && v_type ? 'visualize dynamic':''}' title="Voir le fichier">
											<span class='img'></span>
										</div>
										<div class='icon delete inline medium dynamic' title="Supprimer ${rowdata.status ? 'le fichier':"l'entrée"}">
											<span class='img'></span>
										</div>
										<div class='icon inline medium ${rowdata.status ? 'download dynamic':''}' title="Télécharger le fichier">
											<span class='img'></span>
										</div>`}, resizable: false, frozen: true},
					{ title: "Nom du fichier", field: "name", mutator: function(value){return value.filename()}, formatter: function(cell) {return cell.getValue() + '.' + cell.getRow().getData().extension}, editor: 'input', validator: 'unique', vertAlign: 'middle'},
					{ title: "Extension", field: "extension", visible: false, mutator: function(value, data) {return data.url.extension()}},
					{ title: "Localisation", field: "url", vertAlign: 'middle'},
					{ title: "Taille du fichier", field: "size", formatter: function(cell) {return formatBytes(cell.getValue())}, vertAlign: 'middle', hozAlign: 'center'},
					{ title: "Type", field: "type", hozAlign: 'center'},
					{ title: "Utilisations", field: "used_by", hozAlign: 'center'},	
					{ title: "Alias", field: "description", editor: "input", mutator: function(value){return value === null ? '':value}, hozAlign: 'center'},
					{ title: "Propriétaire", field: "uploader", editor: "list", editorParams: {values:users}, hozAlign: 'center'},
					{ title: "Date de création", field: "created_at", hozAlign: 'center'},
					{ title: "Date de modification", field: "updated_at", hozAlign: 'center'},
					{ title: "Accessible", field: "status", mutator: function(value) {return value === 1 ? true:false}, formatter: function(cell) {return cell.getValue() ? 'OUI':'NON';}, hozAlign: 'center'},
				]});
			
			filesTable.on('cellClick', (e, cell) => {
				data = cell.getRow().getData()

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
			})
			
			filesTable.on('tableBuilt', function () {
				statusWizard()
			})

			filesTable.on('pageLoaded', function () {
				statusWizard()
			})

			return sourcedata
			}

// Users table
async function fetchUsers(target) {
			sourcedata = await dataFetcher('users').then(response => response['sql-data']);
			
			usersTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataTable",
				  rowHeight: 36,
				  columns: [
					{ title: "Role", field: "role", editor: "list", editorParams: {values:{"user":"user","admin":"admin"}}, editable: adminIntegrity, resizable: false},
					{ title: "Username", field: "username", editor:"input", validator: ['unique', 'required'], editable: userIntegrity},
				  ],
				});
			
			function adminIntegrity(cell) {
				rowData = cell.getRow().getData();
				return (rowData.username !== 'admin' && rowData.username !== data.user.name);
				}
			
			function userIntegrity(cell) {
				rowData = cell.getRow().getData();
				return (rowData.id === data.user.id && rowData.username !== 'admin');
				}
			
			return sourcedata
			}

// Tags table
async function fetchTags(target) {
			sourcedata = await dataFetcher('tags').then(response => response['sql-data']);
			
			tagsTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataStretch",
				rowHeight: 36,
				columns: [
					{ title: "", field: "actions", mutator: function (value, data) {return data.slug}, formatter: function (cell) {
							return `<span>
										<div class='icon delete inline medium dynamic' title="Supprimer l'étiquette">
											<span class='img'></span>
										</div>`}, resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", mutator: function (value, data) {return value.trim()}, validator: tagUniqueness},
				  ],
				});
			
			tagsTable.on('cellClick', (e, cell) => {
			if (e.target.findParent('.icon.delete').nodeName !== 'BODY') {
					pendingAction.function = deleter;
					pendingAction.link = [e, cell];
					Overlay('auth').toggle()
				}
			})

			function tagUniqueness(cell, value) {
				thisVal = cell.getRow().getData().slug;
				
				// extract column values
				data = Array.from(tagsTable.getColumn('slug').getCells()).map(c => {return c.getValue()}).filter(val => val !== thisVal);
				
				return !data.includes(value.urlForm())
				}

			tagsTable.on('cellEdited', cell => {
						cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())
				})
			
			return sourcedata
			}
	
// Tags table
async function fetchTypes(target) {
			sourcedata = await dataFetcher('types').then(response => response['sql-data']);
			
			colorMatchTable = {}
			colorCount = 0;

			typesTable = new Tabulator(target, {
				  data: sourcedata,
				  layout: "fitDataStretch",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: function (value, data) {return data.slug}, formatter: function (cell) {
							return `<span>
										<div class='icon ${cell.getRow().getData().slug !== 'undefined' ? 'delete dynamic' : ''} inline medium ' ${cell.getRow().getData().slug !== 'undefined' ?'title="Supprimer la catégorie"':''}>
											<span class='img'></span>
										</div>`}, resizable: false},
					{ title: "Identifiant", field: "slug", editor: 'input', editable: cell => cell.getRow().getData().slug !== 'undefined', validator: ['unique', 'required']},
					{ title: "Nom", field: "name", editor: "input", editable: cell => cell.getRow().getData().slug !== 'undefined', mutator: function (value, data) {return value.trim()}, validator: typeUniqueness},
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
																						cell.edit()
																					}
																				}

																				colorCount++;
																				return input
					}, editor: 'input', editable: () => false}, // allow use of cell.edit() but blocks direct user interaction
				  ],
				});
			
			typesTable.on('cellClick', (e, cell) => {
			if (e.target.findParent('.icon.delete').nodeName !== 'BODY') {
					pendingAction.function = deleter;
					pendingAction.link = [e, cell];
					Overlay('auth').toggle()
				}
			})

			function typeUniqueness(cell, value) {
				thisVal = cell.getRow().getData().slug;
				
				// extract column values
				data = Array.from(typesTable.getColumn('slug').getCells()).map(c => {return c.getValue()}).filter(val => val !== thisVal);
				
				return !data.includes(value.urlForm())
				}
			
			typesTable.on('cellEdited', cell => {
					if (cell.getField() !== 'color') {
						cell.getRow().getCell('slug').setValue(cell.getValue().urlForm())
					} else if (cell.getField() === 'color') {
						autoEditedCells[cell.getRow().getData().id] = cell
					}
				})
			
			return sourcedata
			}

// Articles table
async function fetchArticles(target) {
			sourcedata = await dataFetcher('articles');
			
			statusMatch = {'published': 'Publié', 'archived': 'Archivé', 'draft': 'Brouillon'}

			articlesTable = new Tabulator(target, {
				  data: sourcedata['sql-data'],
				  layout: "fitDataFill",
				  rowHeight: 36,
				  columns: [
					{ title: "", field: "actions", mutator: function (value, data) {return data.url}, formatter: function (cell) {
							const value = cell.getRow().getData().status;
							return `<span>
										<div class='icon modify ${value !== 'published' ? 'edit dynamic':''} inline medium' title="Modifier l'article">
											<span class='img'></span>
										</div>
										<div class='icon delete inline medium dynamic' title="Supprimer l'article">
											<span class='img'></span>
										</div>
										<div class='icon visualize ${value === 'published' ? 'view':(value === 'archived' ? 'preview':'draft')} inline medium dynamic' title="Voir l'article">
											<span class='img'></span>
										</div>
										<div class='icon ${value === 'published' ? 'archive':(value === 'archived' ? 'unarchive':'paper')} inline medium dynamic' title="${value === 'published' ? "Archiver l'article":(value === 'archived' ? "Désarchiver l'article":"Publier l'article")}">
											<span class='img'></span>
										</div>`}, resizable: false},
					{ title: "Statut", field: "status", formatter: function(cell) {cell.getElement().classList.toggle(cell.getValue()); return statusMatch[cell.getValue()]}, vertAlign: 'middle'},
					{ title: "URL", field: "url", editor:"input", validator: ['unique', 'required']},
					{ title: "Titre", field: "title", editor:"input", validator: "required"},
					{ title: "Auteur", field: "author", editor:"input"},
					{ title: "Date", field: "date", editor:"date", resizable: false},
					{ title: "Date (fin)", field: "end_date", editor:"date", resizable: false},
					{ title: "Type", field: "type", mutator: function (value, data) {return value === '' ? '0':value}, formatter: function(cell) {return cell.getValue() === '0' ? 'Indéfini' : typesCounter[cell.getValue()]}, editor:"list", editorParams: {values:typesCounter}},
					{ title: "Étiquettes", field: "tags", mutator: function(value, data) {return value}, formatter: function(cell) {
																																	text = '';
																																	val = cell.getValue();
																																	val !== null ? JSON.parse(val).forEach(e => {try {text += tagsMatchTable[e].name+', '} catch (e) {null}}): text += ', ';
																																	return text.slice(0,-2)
																																	}}
					// { title: "Image", field: "picture_path", editor:"input", widthShrink:1},
					],
				});
			
			articlesTable.on('cellClick', (e, cell) => {
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
						pendingAction.link = `/admin/tools/article/preview/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/article/preview/${cell.getValue()}`)
					}
				} else if (e.target.findParent('.icon.visualize.draft').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/admin/tools/article/draft/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/article/draft/${cell.getValue()}`)
					}
				} else if (e.target.findParent('.icon.delete').nodeName !== 'BODY') {
						pendingAction.function = deleter;
						pendingAction.link = [e, cell];
						Overlay('auth').toggle()
				} else if (e.target.findParent('.icon.modify.edit').nodeName !== 'BODY') {
					if (!activeTable.integrity) {
						pendingAction.function = redirect;
						pendingAction.link = `/admin/tools/editor/article/${cell.getValue()}`;
						Overlay('validate').toggle()
					} else {
						redirect(`/admin/tools/editor/article/${cell.getValue()}`)
					}
				}
			})

			return
			}

// Logs table
async function fetchLogs(target) {
			sourcedata = await dataFetcher('logs').then(response => response['sql-data']);
			
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
			sourcedata = await fetchAPI('/api/helloasso/get/all').then(response => response.body)
			
			// event match table
			matchTypeTable = {'Shop':'Boutique', 'Event':'Billeterie', 'Donation':'Don', 'Membership':'Adhésion'}
			
			// forms match table - key is form as it is what we find in ordersList
			matchFormsTable = {};
			sourcedata.formsList.forEach(item => {
				matchFormsTable[item.formSlug] = {'type': matchTypeTable[item.formType], 'url': item.url, 'name': item.title}
			})
			
			haTable = new Tabulator(target, {
				data: sourcedata.ordersList,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200, 500],
				paginationCounter:"rows",
				columns: [
				{ title: "", field: "receipts",  mutator: function (value, data) {return data.payments !== null ? data.payments[0].paymentReceiptUrl:null},
																	formatter: function (cell) {
																								content = '';
																								content += cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
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
				{ title: "Payment ID", field: "paymentsID", mutator: function (value, data) {return data.payments !== null ? data.payments[0].id:null}, resizable: false},
				{ title: "Date", field: "date", formatter: function (cell) {v = cell.getValue().split('+')[0]; return v.split('T')[0].replaceAll('-','/') + ' ' + v.split('T')[1]}, resizable: false},
				{ title: "Mail", field: "payer.email"},
				{ title: "Identité", field: "fullname", mutator: function (value, data) {return data.payer.firstName + ' ' + data.payer.lastName.toUpperCase()}, headerFilter: true},
				/*{ title: "Type", field: "formType", mutator: function (value, data) {
																					return matchTypeTable[value]
																					},
				},*/
				{ title: "Formulaire", field: "formName", mutator: function (value, data) {
																					return matchFormsTable[data.formSlug].name
																					}, headerFilter:'list', headerFilterParams:function(){val={};Object.keys(matchFormsTable).forEach(ite => {val[matchFormsTable[ite].name] = matchFormsTable[ite].name});return {'values': val}}
				},
				{ title: "Montant", field: "amount.total", width: 100, mutator: function (val) {return val/100}, formatter: function (cell) {v = cell.getValue().toString(); return v.replace('.',',') + ' €'}, hozAlign: "center"},
				{ title: "Items", field: "itemslist", mutator: function (value, data) {
																						list = {};
																						data.items.forEach(item => {
																							if (list[item.name] === undefined) {
																								list[item.name] = 1;
																							} else {
																								list[item.name] += 1;
																							}
																						})
																						
																						text = '';
																						Object.keys(list).forEach(key => {
																							text += `${key} (${list[key]});`;
																						})
																						
																						return text
																						}, formatter: function (cell) {return `<small>${cell.getValue()}</small>`}
				},
			]});
            
			return sourcedata
			}

// HelloAsso Goodies table
async function fetchGoodies(target) {
			sourcedata = await fetchAPI('/api/helloasso/get/goodies').then(response => response.body)
			
			itemsType = ["Pin's arcENSiel", "Pin's Mouflon", "Sticker", "Briquet", "Affiche"]

			goodiesTable = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200],
				paginationCounter:"rows",
				columns: [
				{ title: "", field: "delivered", resizable: false, formatter: function(cell) {
																							editable = cell.getElement().getAttribute('editable') !== null;
																							cell.getElement().unset('editable')
														
																							return `<input type='checkbox' ${cell.getValue() ? 'checked':''} ${editable ? '':'readonly'}>`
																						}, frozen: true},
				{ title: "", field: "receipts", formatter: function (cell) {
														content = cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
																									<span class='img'></span>
																								</a>`:'';
														return '<span>' + content + '</span>'
														}, resizable: false, frozen: true
				},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: function (cell) {v = cell.getValue().split('+')[0]; return v.split('T')[0].replaceAll('-','/') + ' ' + v.split('T')[1]}, resizable: false},
				{ title: "Mail", field: "mail"},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: function (val) {return val/100}, formatter: function (cell) {v = cell.getValue().toString(); return v.replace('.',',') + ' €'}, hozAlign: "center"}, //bottomCalc:function(values,data){return values.reduce((accumulator, currentValue) => accumulator + currentValue, 0) + ' €'}
				{ title: "Item", field: "item", headerFilter: 'list', headerFilter:'list', headerFilterParams:function(){
																														val={};
																														itemsType.forEach(ite => {val[ite] = ite});
																														return {'values': val}}},
				{ title: "Enregistré sur Paheko ?", field: "registered", mutator: function(value,data) {return value ? value:false}, resizable: false, formatter: function(cell) {
																							editable = cell.getElement().getAttribute('editable') !== null;
																							cell.getElement().unset('editable')
														
																							return `<input type='checkbox' ${cell.getValue() ? 'checked':''} ${editable ? '':'readonly'}>`
																						}, hozAlign: 'center'},
				]});
			
			goodiesTable.on('cellClick', (e, cell) => {
				if (e.target.tagName === 'INPUT') {
					cell.getElement().toggle('editable')
					cell.setValue(e.target.checked)
				}
			})
			
			return sourcedata
			}

// DQ7 Table
async function fetchDQ7(target) {
			sourcedata = await fetchAPI('/api/helloasso/get/drama-queer-7').then(response => response.body)
			
			itemsType = ["Prix conseillé", "Prix minimum", "Prix libre", "Paiement en espèces", "Liste d'attente"]

			dq7Table = new Tabulator(target, {
				data: sourcedata,
				layout: "fitDataFill",
				rowHeight: 36,
				pagination:true,
				paginationSize:20,
				paginationSizeSelector:[5, 10, 20, 50, 100, 200],
				paginationCounter:"rows",
				columns: [
				{title: "", field: "receipts", formatter: function (cell) {
														content = cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon receipt inline medium dynamic' title="Reçu du paiement">
																									<span class='img'></span>
																								</a>`:'';
														return '<span>' + content + '</span>'
														}, resizable: false
				},
				{title: "", field: "ticket", formatter: function (cell) {
														content = cell.getValue() !== null ?  `<a href="${cell.getValue()}" target="_blank_" class='icon ticket inline medium dynamic' title="Billet">
																									<span class='img'></span>
																								</a>`:'';
														return '<span>' + content + '</span>'
														}, resizable: false, frozen: true
				},
				{ title: "Transaction ID", field: "id", resizable: false},
				{ title: "Payment ID", field: "paymentsID"},
				{ title: "Date", field: "date", formatter: function (cell) {v = cell.getValue().split('+')[0]; return v.split('T')[0].replaceAll('-','/') + ' ' + v.split('T')[1]}, resizable: false},
				{ title: "Identité", field: "identity", headerFilter: true},
				{ title: "Montant", field: "amount", width: 100, mutator: function (val) {return val/100}, formatter: function (cell) {v = cell.getValue().toString(); return v.replace('.',',') + ' €'}, hozAlign: "center"},
				{ title: "Tier", field: "item", headerFilter: 'list', headerFilter:'list', headerFilterParams:function(){
																														val={};
																														itemsType.forEach(ite => {val[ite] = ite});
																														return {'values': val}}
				},
				{ title: "Étudiant·e ?", field: "student", headerFilter: 'list', headerFilter:'list', headerFilterParams:{'values': {'Oui':'Oui','Non':'Non'}}, hozAlign: 'center'}
				]});
			
			return sourcedata
			}

// Paheko categories table
async function fetchCategories(target) {
			sourcedata = await fetchAPI('/api/paheko/get/composition').then(response => response.body)
			categories = await fetchAPI('/api/paheko/get/categories').then(response => response.body)
			
			// Generate dynamically subsection buttons
			holder = document.querySelector('input[value="categories"] ~ .input-container.checkboxes.subsection')
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
									categoriesTable.clearFilter()
									categoriesTable.setSort([{column:"nom", dir:"asc"}]);
							} else {
									categoriesTable.setFilter("category", "=", tkn)
									categoriesTable.setSort([{column:"nom", dir:"asc"}]);
									}
							})
					})
			
			// create tabulator
			categoriesTable = new Tabulator(target, {
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
					{ title: "Première adhésion", field: "date_inscription", mutator: function(value) {return value !== null ? value.split(' ')[0]:null}}
					],
				});
			
			return sourcedata
			}

// Subscriptions table
async function fetchSubscriptions(target) {
			sourcedata = await fetchAPI('/api/paheko/get/users').then(response => response.body)
			services   = await fetchAPI('/api/paheko/get/services').then(response => response.body)
			
			// Generate dynamically subsection buttons
			holder = document.querySelector('input[value="subscriptions"] ~ .input-container.checkboxes.subsection')
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
			sourcedata = await fetchAPI('/api/paheko/get/goodies').then(response => response.body)
			
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
					{ title: "Prix d'achat", field: "purchase_price", mutator: function (val) {return val/100}, formatter: function (cell) {v = cell.getValue().toString(); return v.replace('.',',') + ' €'}, hozAlign: "center"},
					{ title: "Prix de vente", field: "price", mutator: function (val) {return val/100}, formatter: function (cell) {v = cell.getValue().toString(); return v.replace('.',',') + ' €'}, hozAlign: "center"},
					{ title: "Stock", field: "stock", sorter: 'number'},
					{ title: "Description", field: "description", resizable: false}
					],
				});
			
			return sourcedata
			}

//// UTILS FUNCTIONS
// Fetch SQL data
async function dataFetcher(type, extra=null) {
            queryData = {'action':'fetch', 'type':type, 'csrf':csrfToken}
		    
            if (extra) {// extra query arguments
                Object.fromEntries(extra).forEach(key, val => {queryData[key] = val})
            }

            jsonData = JSON.stringify(queryData)
		    data = await fetchAPI('/admin/engine/database', jsonData).then(response => response.body)

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