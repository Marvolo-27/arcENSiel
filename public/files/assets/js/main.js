//// VARIABLES ////
const sleep = (delay) => new Promise((resolve) => setTimeout(resolve, delay));
let body;
let csrfToken;

// screen dpi
function scrResol() {dpi=null;for (i=0; i<600; i++) {window.matchMedia(`(resolution:${i}dpi)`).matches ? dpi=i:null};return dpi}

// common variables
let typesList = {}
let tagsMatchTable = {}
let typesMatchTable = {}
const mimeTable = {'application/pdf': 'pdf.svg', 'application/zip': 'archives.svg', 'application/vnd.sqlite3': 'databases.svg'}

// asynchronous common variables
fetch('/tmp/tags.json').then(response => response.json()).then(data => {tagsMatchTable = data; Object.values(tagsMatchTable).forEach(val => tagsList[val.slug] = val.name)}).catch(e => {return {}})
fetch('/tmp/types.json').then(response => response.json()).then(data => {typesMatchTable = data; Object.values(typesMatchTable).forEach(val => typesList[val.slug] = val.name)}).catch(e => {return {}})

// load images
fetch('/files/icons/spinner.svg')

// overlay dom config
window.addEventListener('load', config);
function config()  {
			body = document.body

			csrfToken = Input('csrf')?.dataset.csrf
		}

//// METHODS /////
// Add wrap method to str to shorten str formatting
String.prototype.strForm = function () {return this.trimStart().trimEnd().toLowerCase()}

// url/path utils
String.prototype.filename = function (extension=false) {
														result = this.split('/').slice(-1)[0];
														
														if (extension) {
															return result
														} else {
															return result.split('.').length > 1 ? result.split('.').slice(0,-1).join('.') : result
														}
														}
String.prototype.extension = function () {
										filename = this.split('/').slice(-1)[0];
										return filename.split('.').length > 1 ? filename.split('.').slice(-1)[0] : ''
										}

// method to convert string to compatible Link
	// normalise separate accentuated letters in {accent + base letter}
	// \u0300-\u036f match accents range
String.prototype.urlForm = function () {return this.strForm().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s/g, '-')}
String.prototype.filenameForm = function () {return this.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s/g, '_')}

// Add method to str to turn a query selector to a list of selectors (the initial query must be only same-index-level query : no sibling/parent selectors)
// If node name is given, is assumed to be the first element of the query
String.prototype.toSelectorList = function () {
												// creates aliases for classes and id to find them afterwards
												text = this.replaceAll('.','.££').replaceAll('#','°°');
												list = [];
												text.split('.').forEach((l) => {
																				l.split('#').forEach((e) => {
																											e !==''? list.push(e.replace('££', '.').replace('°°','#')) : null;
																											})
																				})
												return list
												}

// Add method to create a text query for input element | argument is a boolean to choose list (flase) or concatenate input (true)
Element.prototype.toSelector = function(conc) {
											selector = conc ? '':[]
											
											// add node name
											elName = this.nodeName.toLowerCase();
											conc ? selector += elName : selector.push(elName);
											
											//add id
											elId = this.id !== '' ? '#'+this.id:'';
											conc ? (selector += elId):null;
											!conc && elId !== '' ? (selector.push(elId)):null;
											
											// add class
											this.className.split(' ').forEach((c) => {
																						c = c !== '' ? '.'+c:'';
																						conc ? selector += c:null;
																						!conc && c ? selector.push(c):null;
																						})
											return selector
											}

// Add method to find specified parent node | closest corresponding parent returned; assumes at least one parent exists
// selector is for a single node : sibling/parents selectors like +>~ are ignored
Element.prototype.findParent = function(selector) { 
													selList = selector.toSelectorList()
													
													elm = this
													while (elm.nodeName !== 'BODY') {
																					check = true;
																					currSel = elm.toSelector(false);
																					
																					// checks the presence of every provides selector in current element selectors
																					selList.forEach((sel) =>   {
																												// checks turns and remains false at first selector incompatibility
																												check = (check && currSel.includes(sel)) ? true:false
																												})
																					
																					// conclusion
																					if (check) {return elm} else {elm = elm.parentNode}
																					}
													return document.querySelector('body')
													}

// Alias method for querySelector
Element.prototype.child = function(selector) {return this.querySelector(selector)}
Element.prototype.childs = function(selector) {return this.querySelectorAll(selector)}

// Shortcuts to toggle attributes
Element.prototype.set = function(attr) {this.setAttribute(attr, '')}
Element.prototype.unset = function(attr) {this.removeAttribute(attr)}
Element.prototype.toggle = function(attr='active') {this.toggleAttribute(attr)}

Element.prototype.show = function() {this.set('active')}
Element.prototype.hide = function() {this.unset('active')}

Element.prototype.enable = function() {this.unset('disabled')}
Element.prototype.disable = function() {this.set('disabled')}

// Shortcut to find common tags
function Overlay(selector, parent=document) {return parent.querySelector(`.body-overlay${selector ? '#overlay-':''}${selector}`)}

function Button(selector, parent=document) {return parent.querySelector(`.action-btn${selector ? '.':''}${selector}`)}
Element.prototype.button = function(selector='') { 
											return Button(selector, this)
											}

function Icon(selector, parent=document) {return parent.querySelector(`.icon${selector ? '.':''}${selector}`)}
Element.prototype.icon = function(selector='') { 
											return Icon(selector, this)
											}

function Input(selector, parent=document) {return parent.querySelector(`${selector ? '#':'input'}${selector}`)}
Element.prototype.input = function(selector='') { 
											return Input(selector, this)
											}

function Error(selector, parent=document) {return parent.querySelector(`.error${selector ? '.':''}${selector}`)}
Element.prototype.error = function(selector='') { 
											return Error(selector, this)
											}

function Box(selector, parent=document) {return parent.querySelector(`.box${selector ? '.':''}${selector}`)}
Element.prototype.box = function(selector='') { 
											return Box(selector, this)
											}

function Spinner(parent=document) {return parent.querySelector('.spinner')}
Element.prototype.spinner = function() { 
										return Spinner(this)
										}

// Find closest 'special' parent node (Box, Overlay), or itself if there is no upper container layer
Element.prototype.container = function() { 
											if (this.findParent('.box').nodeName !== 'BODY') {
												return this.findParent('.box')
											} else if (this.findParent('.body-overlay').nodeName !== 'BODY') {
												return this.findParent('.body-overlay')
											} else {
												return this
											}
										}
Event.prototype.container = function() {return this.target.container()}

//// FUNCTIONS ////
// LAYOUT //
// show navbar in tight-window mode
function toggleNav(e) {
						duration = 400;
						e = e.findParent('.nav-toggle');
						if (e.className.includes('down')) {
														e.className = e.className.replace(' down','');
														sliderUp(e, duration, '.nav-wrap', '.headers');
						} else {
								e.className += ' down';
								sliderDown(e, duration, '.nav-wrap', '.headers');
						}
					}

// show submenu
function toggleSubMenu(e, force=false) {
						duration=400;
						const menu = e.findParent('.nav-item');
						
						if (menu.getAttribute('active') === null || force === 'in') {
								menu.show();
								sliderDown(menu, duration, '.nav-item', '.sub-menu');
						} else if (menu.getAttribute('active') !== null || force === 'out') {
								menu.hide();
								sliderUp(menu, duration, '.nav-item', '.sub-menu');
						}
						}


// sliders
function sliderUp(e, duration, father, cible) {
								const targ = e.findParent(father).querySelector(cible)
								targ.style.transition= `all ${duration}ms`;
								targ.style.height = `${targ.offsetHeight}px`;
								targ.style.overflow = 'hidden';
								
								setTimeout(() => {
									targ.style.height = 0;
									}, 5)
								
								setTimeout(() => {
									targ.removeAttribute('style');
									targ.setAttribute('hidden', 'true');
									}, duration)
								}

function sliderDown(e, duration, father, cible) {
								targ = e.findParent(father).querySelector(cible)
								targ.style.visibility = 'hidden';
								targ.setAttribute('hidden', 'false');;
								targ.style.overflow = 'hidden';
								let fullheight = targ.offsetHeight;
								targ.style.transition= `all ${duration}ms, margin-top ${duration}ms`;
								targ.style.height = 0;
								targ.style.removeProperty('visibility');
								
								setTimeout(() => {
									targ.style.height = `${fullheight}px`;
									}, 5)
								
								setTimeout(() => {
									targ.removeAttribute('style');
									}, duration)
								}

// handle banner visibility
async function bannerManage()  {
									y = window.scrollY;
									logo = document.querySelector('.nav-logo.logo');
									login = document.querySelector('.nav-logo.login');
									
									banner = document.querySelector('#banner');
									bannerHeight = banner.offsetHeight;
									bannerItems = banner.querySelectorAll('.banner-img');
									
									if (y > bannerHeight && !logo.className.includes('show')) {
										logo.className = logo.className.replace('hide','show')
									} else if (y <= bannerHeight && !banner.className.includes('hide')) {
										logo.className = logo.className.replace('show', 'hide')
									}
								}

// banner movement
async function logoWaveToggleToFlat() {
							banner = document.querySelector('.banner-logo');
							banner.className = 'banner-logo flat'
								
							await sleep(350)
							for (i=1;i<10;i++) {
								document.querySelector(`.banner-logo .banner-letter:nth-child(${i})`).setAttribute('mode','flat')
								await sleep(50)
							}
							
							banner.className = banner.className.replace(' flat', '');
							
							outlocked = false;
						}

async function logoWaveToggleToWave() {
							banner = document.querySelector('.banner-logo');
							banner.className = 'banner-logo flat'
								
							banner.className = 'banner-logo wave'
								
							await sleep(350)
							for (i=1;i<10;i++) {
								document.querySelector(`.banner-logo .banner-letter:nth-child(${i})`).setAttribute('mode','wave')
								await sleep(50)
							}
							
							banner.className = banner.className.replace(' wave', '')
							}

// GENENRAL CONFIG //
window.onload = () => {
	document.querySelector('.banner-logo').onmouseenter = logoWaveToggleToFlat;
	document.querySelector('.banner-logo').onmouseleave = logoWaveToggleToWave;
	
	// handle banner shape
	window.addEventListener('scroll', bannerManage);

	// handle login form
	try {
		window.matchMedia(`(hover: none)`).matches ? document.querySelector('.nav-logo.login').findParent('.nav-logo-wrap').onclick = authWrap:null;
		document.querySelector('.auth-wrap').icon('cross').onclick = authWrap;
	} catch (error) {null}

	// password input
	document.querySelectorAll('.toggle-pass')?.forEach(tag => {tag.onclick = e => togglePassword('#'+ e.target.findParent('.icon').previousElementSibling.id)});
	
	// handle navbar
	try {
		Array.from(document.querySelectorAll('.nav-item:has(.sub-menu)')).forEach(navitem => {
			if (window.matchMedia(`(hover:hover)`).matches) {
				navitem.onmouseenter = e => toggleSubMenu(e.target, 'in');
				navitem.onmouseleave = e => toggleSubMenu(e.target, 'out');
			}
			if (window.matchMedia(`(hover:none)`).matches) {
				navitem.onclick = e => toggleSubMenu(e.target);
			}
		})
	} catch (error) {null}
}

// Show auth Form
function authWrap(e) {
					wrap = document.querySelector('.nav-logo.login').findParent('.nav-logo-wrap');
					
					auth = wrap.querySelector('.auth-wrap');
					
					Array.from(e.target.classList).includes('img') ? auth.hide():auth.show();
					}

// toggle password visibility
function togglePassword(selector) {
					input = document.querySelector(selector);
					input.focus();
					icon = input.nextElementSibling.firstElementChild;
					
					icon.toggle();
					
					if (icon.getAttribute('active') === null) {
						input.setAttribute('type', 'password');
						icon.setAttribute('title', 'Afficher le mot de passe');
					} else {
						input.setAttribute('type', 'text');
						icon.setAttribute('title', 'Masquer le mot de passe');
					}
				}

// UTILS //
// Date format
function formatDate() {
					  const now = new Date();
					  var date = {}
					  date.year = now.getFullYear();
					  date.month = now.getMonth() + 1;
					  date.day = now.getDate();
					  date.hours = now.getHours();
					  date.minutes = now.getMinutes();
					  
					  return date;
					}

// Size format
function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 Byte';
  const k = 1024;
  const sizes = ['Bytes', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(decimals))} ${sizes[i]}`;
}

// Redirection
function redirect(link) {
		window.location.href = link;
}

// Pop up tab
function newTab(link) {
		open(link);
}

// Download file
function download(blob, name) {
	c = document.createElement("a");
	url = URL.createObjectURL(blob);
				
	c.download = name;
	c.href = url;
	c.click();
	setTimeout(function() { URL.revokeObjectURL(c.href); }, 1500);
}

// API request
async function fetchAPI(url, data=null) {
	// config request
	payload = data ? {method: "POST"} : null;
	data ? payload.body = data : null;
	typeof data === 'string' ? payload.headers = { 'Content-Type': 'application/json' } : null;

	// fetch data
	response = await fetch(url, payload);
	json = await response.json();

	// output
	return {ok: response.ok, httpcode: response.status, body: json}
}

// Get file content
async function fetchBlob(url) {
	response = await fetch(url);
	blob = await response.blob()

	return blob
}

// get cursor position : start position of textContent
function getCaret() {
	var sel = document.getSelection();	// global API that handles selection (what is clicked, selected)
	sel.modify("extend", "backward", "documentboundary");	// extend selection to beginning of current paragraph - granularity may be changed
	
	pos = sel.toString().length;		// get length = caret opffset position
	if (sel.anchorNode != undefined) sel.collapseToEnd();	// collapse selection/cursor to end of selection : reset cusor to initial position
	
	return {'start': pos, 'node': sel.anchorNode.parentNode.toSelector(true)}; // anchorNode is a text node - so convert it to element Node to apply DOM logic
}

// set cursor position
function setCaret(rootNode, start) {
	selection = window.getSelection()	// get current selection
		
	offset = start; // remaining offset
	found = false;	// caret status
	function navigate(node) {// recursive nested function to find and set valid caret position
		if  (node.nodeType === Node.TEXT_NODE) {// nodeType gives num code of node typ, matched with text node code stored in Node API
			// node length
			if (node === rootNode.firstChild) {
				len = node.textContent.replace(/^(\n*\t*)/,'').replaceAll('\n','').length; // manual trim for first node
			} else if (node === rootNode.lastChild) {
				len = node.textContent.replace(/(\t*)$/, '').replaceAll('\n','').length; // manual trim for first node
			} else {
				len = node.textContent.replaceAll('\n','').length;
			}
			
			// compare length to remaining offset
			if (offset > len) {
				offset -= len; // update offset for next text node
			} else {// caret within current node
				range = document.createRange()
				
				range.setStart(node, offset)			// config range
				range.collapse(true)					// reduce selection to a point
				selection.removeAllRanges()				// reset selection
				selection.addRange(range)			// update selection
				
				found = true; // prevent further caret move
			}
		} else {// recursive loop in children
			for (childnode of node.childNodes) !found ? navigate(childnode):null;
		}
	}
	
	navigate(rootNode);
}

// Check password input
async function passwordChecker(selector='input#overlay-pswd') {
					// pswd zone
					tag = document.querySelector(selector);
					pswd = tag.value;
					
					// error message
					error = tag.findParent('.box').error();
					
					// action buttons
					btn = Overlay('auth').button('validate');
					btnbis = Overlay('auth').button('cancel');
					btn.disabled = true;
					btnbis.disable();
					btn.toggle()
					
					// reset state
					tag.unset('valid');
					error.hide();
					
					await sleep(500);
					
					// update data
					jsonData = JSON.stringify({'action':'check', 'csrf':csrfToken, 'password':pswd})
					response = await fetchAPI('/admin/engine/database', jsonData)

					tag.value = '';
					btn.toggle();
					btn.disabled = false;
					btnbis.enable();
					
					if (!response.ok) {
						tag.set('invalid');
						error.show();

						return false
					} else {return true}
			}

// NOTIFICATIONS //
// handle notifications
async function notify(text, color='black', duration=3000) {
					// force local variables to prevent overwrite between simultaneous function calls
					const notifTag = document.createElement('div');
					const notifWrap = document.querySelector('#notifications');
					
					// HTML of notification
					notifTag.innerHTML = `
						<div class='notification-panel'></div>
						<div class='notification-content'>${text}</div>
						`
					notifTag.className = 'item'
					notifWrap.appendChild(notifTag)

					// apply style
					notifTag.querySelector('.notification-panel').style.background = `${color}`
					notifTag.querySelector('.notification-content').style.borderColor = `${color}`
					
					// display
					notifTag.style.visibility = 'hidden'
					width = notifTag.offsetWidth // fetch width
					notifTag.style.width = 0
					await sleep(10);
					notifTag.style.removeProperty('visibility')
					notifTag.style.width = `${1.1 * width}px`

					// notification lifetime
					await sleep(duration);

					// hide
					notifTag.style.width = 0;
					await sleep(300);

					// destroy
					notifTag.remove();
					}

// FILE MANAGEMENTS
// update the 'used_by' entry based on client actions
function usageUpdate(name, rawlist, action='remove') {
		list = rawlist.split(',');

		if (action === 'remove') {
			newlist = list.filter(e => {return e === name ? null:name})
		} else {
			newlist = list;
			newlist.push(name)
		}
		
		newlist = newlist.join(',')
		return newlist == '' ? null:newlist.replace(/,+/, ',').replace(/(^,|,$)/, '')
	}