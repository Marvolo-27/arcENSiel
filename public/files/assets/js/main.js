//// VARIABLES ////
const sleep = (delay) => new Promise((resolve) => setTimeout(resolve, delay));
let body;
let csrfToken;

// screen dpi
function scrResol() {dpi=null;for (i=0; i<600; i++) {window.matchMedia(`(resolution:${i}dpi)`).matches ? dpi=i:null};return dpi}

// common variables
let pendingAction = {}
let typesMatch = {}
let tagsMatch = {}
let tagsList = {}
let typesList = {}
let categoriesList = []
const mimeTable = {'application/pdf': 'pdf.svg', 'application/zip': 'archives.svg', 'application/vnd.sqlite3': 'databases.svg'}

// asynchronous common variables
fetch('/tmp/tags.json').then(response => response.json()).then(data => {tagsList = data; Object.values(tagsList).forEach(val => tagsMatch[val.slug] = val.name)}).catch(e => {return {}})
fetch('/tmp/types.json').then(response => response.json()).then(data => {typesList = data; Object.values(typesList).forEach(val => typesMatch[val.slug] = val.name)}).catch(e => {return {}})
fetch('/tmp/categories.json').then(response => response.json()).then(data => categoriesList = data.reduce((list, cat) => {list.push(cat['slug']); return list}, [])).catch(e => {return {}})

// load images
fetch('/files/icons/spinner.svg')

// Global utils
window.addEventListener('load', config);
function config()  {
		body = document.body
		csrfToken = Input('csrf')?.dataset.csrf

		/// Dynamic elements config ///
		// handle banner
		Section('banner-logo').onmouseenter = () => logoWaveToggle(false)
		Section('banner-logo').onmouseleave = () => logoWaveToggle(true)
		
		// handle navbar logo
		window.addEventListener('scroll', bannerManage)

		// handle login form
		if (window.matchMedia(`(hover: none)`).matches && Section('auth-wrap')) {
			Section('nav-logo.login').parent('.nav-logo-wrap').onclick = authWrap
			Section('auth-wrap').icon('cross').onclick = authWrap
		}

		// password input
		Icons('toggle-pass').forEach(tag => tag.onclick = togglePassword)
		
		// handle navbar
		Array.from(Sections('.nav-item:has(.menu)')).forEach(navitem => {
			if (!(window.matchMedia(`(hover:hover)`).matches && window.matchMedia(`(min-width:642px)`).matches)) {
				navitem.onclick = toggleSubMenu
			}
		})
	}

//// METHODS /////
// Alias to filter arrays to unique values
Object.defineProperty(Array.prototype, 'filterUnique', {
  value: function() {return this.filter((item, index, arr) => (item !== null && arr.indexOf(item) === index))},
  enumerable: false,
})

// Add wrap method to str to shorten str formatting
	// normalise separate accentuated letters in {accent + base letter}
	// \u0300-\u036f match accents range
String.prototype.strForm = function () {return this.trimStart().trimEnd().toLowerCase()}
String.prototype.urlForm = function () {return this.strForm().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s/g, '-')}
String.prototype.filenameForm = function () {return this.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[\s\/<>:\*\?"\|\\]/g, '_')}

// url/path utils
String.prototype.filename = function (extension=false) {
			result = this.split('/').slice(-1)[0];
			return extension ? result : result.split('.').length > 1 ? result.split('.').slice(0,-1).join('.') : result
			}
String.prototype.extension = function () {
			filename = this.split('/').slice(-1)[0];
			return filename.split('.').length > 1 ? filename.split('.').slice(-1)[0] : ''
			}

// Add method to str to turn a query selector to a list of selectors (the initial query must be only same-index-level query : no sibling/parent selectors)
// If node name is given, is assumed to be the first element of the query
String.prototype.toSelectorList = function () {
			return this.replaceAll(' ','').split('#').map((item, i) => i === 0 ? item : `#${item}`).reduce((list, item) => [...list, ...item.split('.').map((item, i) => i === 0 ? item : `.${item}`)], []).filter(item => !!item)
			}

// Add method to create a text query for input element | argument is a boolean to choose list (flase) or concatenate input (true)
Element.prototype.toSelector = function(concatenate) {
			let selector = [this.nodeName.toLowerCase(), this.id ? `#${this.id}` : null, ...Object.values(this.classList).map(item => `.${item}`)]
			
			return concatenate ? selector.join('') : selector.filter(item => !!item)
			}

function selectorChecker(selector, parent, marker, complement = null, unique = true) {
			let selectors = [complement].concat((!selector ? '' : (selector[0] === marker || !selector[0].match(/^[a-zA-Z_\-]/)) ? selector : marker + selector).toSelectorList()).filter(item => !!item)
			
			if (selectors.length === 0) return null
			
			return selectors.reduce((bool, item) => bool && (Array.isArray(item) ? item.reduce((subbool, subitem) => subbool && parent.toSelector().includes(subitem), true) : parent.toSelector().includes(item)), true) ? 
				parent : unique ?
				parent.querySelector((Array.isArray(complement) ? [`:is(${complement.join(',')})`, ...selectors.slice(1)]:selectors).join('')) : 
				parent.querySelectorAll((Array.isArray(complement) ? [`:is(${complement.join(',')})`, ...selectors.slice(1)]:selectors).join(''))
			}

Element.prototype.is = function(selector) { 
			if (!selector) return false

			const constructorsList = [Box, Button, Error, Icon, Input, Overlay, Table]
			const attributesList = ['active', 'disabled', 'hidden', 'valid', 'invalid', 'empty']

			// Quick search for described tags
			if (constructorsList.reduce((bool, Tag) => bool || Tag(selector, this) !== null, false)) return true
			
			// Quick search for common control attributes
			if (attributesList.includes(selector) && this.getAttribute(selector) !== null) return true

			return selector.toSelectorList().reduce((bool, item) => bool && this.toSelector().includes(item), true)
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
function Box	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '.', '.box') : null}
function Block	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '#') : null}
function Button	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '.', '.action-btn') : null}
function Error	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '.', '.error') : null}
function Icon	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '.', '.icon') : null}
function Input	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '#', ['input','textarea','select','[contenteditable]']) : null}
function Overlay(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '#overlay-', '.body-overlay') : null}
function Table	(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '#', '.table') : null}
function Section(selector, parent=document.body) {return selector != null ? selectorChecker(selector, parent, '.') : null}
function Spinner(parent=document.body) {return parent.querySelector('.spinner')}

function Buttons(selector='', parent=document.body) {return selectorChecker(selector, parent, '.', '.action-btn', false)}
function Errors	(selector='', parent=document.body) {return selectorChecker(selector, parent, '.', '.error', false)}
function Icons	(selector='', parent=document.body) {return selectorChecker(selector, parent, '.', '.icon', false)}
function Inputs	(selector='', parent=document.body) {return selectorChecker(selector, parent, '.', ['input','textarea','select','[contenteditable]'], false)}
function Sections(selector, parent=document.body) {return selectorChecker(selector, parent, '.', null, false)}

Element.prototype.box    = 	function(selector='') {return Box(selector, this)}
Element.prototype.block  = 	function(selector='') {return Block(selector, this)}
Element.prototype.button = 	function(selector='') {return Button(selector, this)}
Element.prototype.error  = 	function(selector='') {return Error(selector, this)}
Element.prototype.icon   = 	function(selector='') {return Icon(selector, this)}
Element.prototype.input  = 	function(selector='') {return Input(selector, this)}
Element.prototype.section = function(selector)	  {return Section(selector, this)}
Element.prototype.spinner = function() {return Spinner(this)}

Element.prototype.buttons = function(selector='') {return Buttons(selector, this)}
Element.prototype.errors  = function(selector='') {return Errors(selector, this)}
Element.prototype.icons   = function(selector='') {return Icons(selector, this)}
Element.prototype.inputs  = function(selector='') {return Inputs(selector, this)}
Element.prototype.sections = function(selector='') {return Sections(selector, this)}

// Add method to find specified parent node | closest corresponding parent returned; assumes at least one parent exists
// selector is for a single node : sibling/parents selectors like +>~ are ignored
Element.prototype.parent = function(selector) { 
			const commons = {
							'icon': '.icon',
							'button': '.action-btn',
							'box': '.box',
							'table': '.table',
							'overlay': '.body-overlay',
							'container': '.input-container'
							}
			
			let selectors = selector.toSelectorList()
			let node = this

			selectors[0] = commons[selectors[0]] ?? selectors[0] // Overwrite first selector for common tags
			
			while (node.nodeName !== 'BODY') {
											if (selectors.reduce((bool, item) => bool && node.toSelector(false).includes(item), true)) break
											
											node = node.parentNode
											}
			return node !== document.body ? node : false
			}

//// FUNCTIONS ////
// LAYOUT //
// show navbar in tight-window mode
function toggleNav(tag, duration=400) {
						const burger = tag.parent('.nav-toggle')
						burger.is('active') ? sliderUp(burger, duration, '.nav-wrap', '.headers') : sliderDown(burger, duration, '.nav-wrap', '.headers')
						burger.toggle()
					}

// show submenu
function toggleSubMenu(e, duration=400) {
						const navitem = e.target.parent('.nav-item')
						const navitems = e.target.parent('.headers').sections('nav-item')
						
						if (!!navitem.parent('.menu')) return

						if (navitem.is('active')) {
								navitem.hide()
								if (!window.matchMedia(`(min-width: 642px)`).matches) sliderUp(navitem, duration, '.nav-item', '.menu')
						} else {
								navitem.show()
								if (window.matchMedia(`(min-width: 642px)`).matches) {
									navitems.forEach(item => item !== navitem ? item.hide():null)
								} else {
									sliderDown(navitem, duration, '.nav-item', '.menu')
								}
						}
						}


// sliders
function sliderUp(e, duration, parent, cible) {
								const tag = e.parent(parent).querySelector(cible)

								tag.style.transition= `all ${duration}ms`;
								tag.style.height = `${tag.offsetHeight}px`;
								tag.style.overflow = 'hidden';
								
								setTimeout(() => {
									tag.style.height = 0;
									tag.style.marginTop = 0;
									}, 5)
								
								setTimeout(() => {
									tag.removeAttribute('style')
									tag.hide()
									}, duration)
								}

function sliderDown(e, duration, parent, cible) {
								const tag = e.parent(parent).querySelector(cible)
								const margin = window.getComputedStyle(tag).marginTop ?? 0
								tag.style.visibility = 'hidden';
								tag.style.overflow = 'hidden';

								tag.show()
								let fullheight = tag.offsetHeight;

								tag.style.transition= `all ${duration}ms, margin-top ${duration}ms`;
								tag.style.height = 0;
								tag.style.removeProperty('visibility');
								
								setTimeout(() => {
									tag.style.height = `${fullheight}px`;
									tag.style.marginTop = margin;
									}, 5)
								
								setTimeout(() => {
									tag.removeAttribute('style');
									}, duration)
								}

// handle banner visibility
async function bannerManage()  {
								const y = window.scrollY
								const logo = document.querySelector('.nav-logo.logo')
								const bannerHeight = Block('banner').offsetHeight
								
								y > bannerHeight ? logo.show() : logo.hide()
								}

// banner movement
async function logoWaveToggle(reverse) {
							const banner = Section('banner-logo')
							banner.classList.toggle(reverse ? 'wave':'flat')
							
							await sleep(350)
							for (i=1;i<10;i++) {
								banner.child(`.banner-letter:nth-child(${i})`).setAttribute('mode', reverse ? 'wave':'flat')
								await sleep(50)
							}
							
							banner.classList.toggle(reverse ? 'wave':'flat')
						}

// GENENRAL CONFIG //
// Show auth Form
function authWrap(e) {
					const wrap = Section('nav-logo.login').parent('.nav-logo-wrap')
					const auth = wrap.section('auth-wrap')
					
					e.target.is('icon.cross') ? auth.hide():auth.show()
					}

// toggle password visibility
function togglePassword(e) {
					const icon = e.target
					const input = icon.previousElementSibling
					
					input.focus()
					icon.toggle()
					
					if (icon.is('active')) {
						input.setAttribute('type', 'text')
						icon.setAttribute('title', 'Masquer le mot de passe')
					} else {
						input.setAttribute('type', 'password')
						icon.setAttribute('title', 'Afficher le mot de passe')
					}
				}

// UTILS //
// Date format
function formatDate() {
					  const now = new Date();
					  var date = {}
					  date.year = now.getFullYear()
					  date.month = now.getMonth() + 1;
					  date.day = now.getDate()
					  date.hours = now.getHours()
					  date.minutes = now.getMinutes()
					  
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
		window.location.href = link
}

// Pop up tab
function newTab(link) {
		open(link)
}

function transfer(e, bool=true) {
		e.target.parent('overlay').hide()
		if (bool) pendingAction.link ? pendingAction.function(pendingAction.link):pendingAction.function()
		pendingAction = {}
}

// Download file
function download(blob, name) {
	const c = document.createElement("a")
	const url = URL.createObjectURL(blob)
				
	c.download = name;
	c.href = url;
	c.click()
	setTimeout(function() { URL.revokeObjectURL(c.href); }, 1500)
}

// View file
function viewFile(raw, mime, activeOverlay=null) {
	const url = raw.replace(/admin:\/\//, '/admin')
	const [type, extension] = mime.split('/')
	
	if ((type === 'application' && ['pdf', 'sql'].includes(extension)) || type === 'text') {
		Overlay('preview').section('file-preview').child('iframe').src = url
		Overlay('preview').section('file-preview').child('iframe').show()
	} else if (type === 'image') {
		Overlay('preview').section('file-preview').child('img').src = url
	}
	Overlay('preview').toggle()
	Overlay(activeOverlay)?.toggle()
	pendingAction = {'overlay': activeOverlay}
}

// API request
async function fetchAPI(url, data=null) {
	// config request
	const payload = data ? {method: "POST"} : null;
	data ? payload.body = data : null;
	typeof data === 'string' ? payload.headers = { 'Content-Type': 'application/json' } : null;

	// fetch data
	const response = await fetch(url, payload)
	const json = await response.json()

	// output
	return {ok: response.ok, httpcode: response.status, body: json}
}

// Get file content
async function fetchBlob(url) {
	const response = await fetch(url)
	const blob = await response.blob()

	return blob
}

// get cursor position : start position of textContent
function getCaret() {
	var sel = document.getSelection()	// global API that handles selection (what is clicked, selected)
	sel.modify("extend", "backward", "documentboundary")	// extend selection to beginning of current paragraph - granularity may be changed
	
	pos = sel.toString().length			// get length = caret offset position
	if (sel.anchorNode != undefined) sel.collapseToEnd()	// collapse selection/cursor to end of selection : reset cusor to initial position
	
	return {'start': pos, 'node': sel.anchorNode.parentNode.toSelector(true)}	// anchorNode is a text node - so convert it to element Node to apply DOM logic
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
				len = node.textContent.replace(/^(\n*\t*)/,'').replaceAll('\n','').length;	// manual trim for first node
			} else if (node === rootNode.lastChild) {
				len = node.textContent.replace(/(\t*)$/, '').replaceAll('\n','').length; 	// manual trim for first node
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
				selection.addRange(range)				// update selection
				
				found = true; // prevent further caret move
			}
		} else {// recursive loop in children
			for (childnode of node.childNodes) !found ? navigate(childnode):null;
		}
	}
	
	navigate(rootNode)
}

function compareWithOldValue(e, core='') {
			const input = e.target
			const error = input.parent('box').error()
			
			if (input.value.replace(core, '') !== input.getAttribute('previous-value')) {
				input.unset(input.dataset.validity)
				error.hide()
			} else {
				input.set(input.dataset.validity ?? '')
				if (input.dataset.validity === 'invalid') error.show()
			}

			if (!input.value.includes(core)) input.value = core
		}

async function checkInput(input, script, type, core='') {
			// DOM elements
			const spin = input.parent('container').spinner()
			const error = input.parent('box').error()
			const value = input.value.replace(core,'').urlForm()
			const mode = input.disabled ? 'locked':'unlocked'

			if (!new RegExp(input.getAttribute('pattern') ?? '').test(value)) {
				input.setCustomValidity("Format incorrect")
				input.reportValidity()
				return
			} else {input.setCustomValidity('')}
			if (value === input.getAttribute('initial-value')) {
				submitAllower(true)
				return
			}
			if (value === input.getAttribute('previous-value')) {
				submitAllower()
				return
			}
			if (value === '') return
			
			// contents
			if (!input.disabled) input.value = input.value.urlForm()		// overrides latest input
			input.unset(input.dataset.validity)
			error.hide()
			spin.show()

			await sleep(250)
			
			const response	= await fetchAPI(`${script}?action=check&type=${type}&mode=${mode}&content=${value}&token=${csrfToken}`)
			
			if (!response.ok) {
				input.dataset.validity = 'invalid'
				error.show()
			} else {
				input.dataset.validity = 'valid'
				input.value = core + (response.body.value ?? value)
			}

			input.setAttribute('previous-value', response.body.value ?? value)
			input.set(input.dataset.validity)
			spin.hide()
			submitAllower()
		}

// Check password input
async function passwordChecker(selector='input#overlay-pswd') {
					// pswd zone
					const tag = document.querySelector(selector)
					pswd = tag.value
					
					// error message
					const error = tag.parent('box').error()
					
					// action buttons
					const validate = Overlay('auth').button('validate')
					const cancel = Overlay('auth').button('cancel')
					
					// block buttons
					validate.disabled = true
					cancel.disable()
					validate.toggle()
					
					// reset state
					tag.unset('valid')
					error.hide()
					
					await sleep(500)
					
					// update data
					const jsonData = JSON.stringify({'action':'auth', 'csrf':csrfToken, 'password':pswd})
					const response = await fetchAPI('/admin/engine/database', jsonData)

					// restore buttons
					tag.value = '';
					validate.toggle()
					validate.disabled = false
					cancel.enable()
					
					if (!response.ok) {
						tag.set('invalid')
						error.show()

						return false
					} else {return true}
			}

// NOTIFICATIONS //
// handle notifications
async function notify(header, text, color='orange', duration=4000) {
					// force local variables to prevent overwrite between simultaneous function calls
					const notifTag = document.createElement('div')
					const notifWrap = Block('notifications')
					
					// HTML of notification
					notifTag.innerHTML = `
						<div class='notification-panel'></div>
						<div class='notification-content'>
							<div class="notification-title">${header}</div>
							<div class="notification-body">${text}</div>
						</div>
						<div class='notification-timer'>
							<div class="notification-close icon cross small inline hoverable" forced>
								<svg class="timer" viewbox="0 0 1000 1000">
									<circle cx="500" cy="500" r="450" stroke="silver" fill="none" stroke-width="75"></circle>
									<circle class="timer-stroke" cx="500" cy="500" r="450"></circle>
								</svg>
							</div>
							
						</div>
						
						`
					notifTag.className = 'item'
					notifWrap.prepend(notifTag)

					// apply style
					notifTag.querySelector('.notification-panel').style.background = `${color}`
					notifTag.querySelector('.notification-content').style.borderColor = `${color}`
					notifTag.querySelector('.notification-timer').style.borderColor = `${color}`
					notifTag.querySelector('.timer-stroke').style.stroke = `${color}`
					notifTag.querySelector('.timer-stroke').style.transitionDuration = `${duration/1000}s`
					
					// closer
					notifTag.icon('cross').onclick = async e => {
						const tag = e.target.parent('.item')
						
						tag.style.transform = `translateX(20px)`	
						tag.style.opacity = 0
						
						await sleep(300)
						tag.remove()
					}

					// display
					notifTag.style.transform = `translateX(-20px)`	
					notifTag.style.opacity = 0

					// show
					await sleep(300)
					notifTag.style.removeProperty('transform')
					notifTag.style.removeProperty('opacity')
					notifTag.querySelector('.timer-stroke').style.strokeDashoffset = `calc(-1 * var(--p))`

					// notification lifetime
					await sleep(duration);

					// hide
					notifTag.style.transform = `translateX(20px)`	
					notifTag.style.opacity = 0
					
					// destroy
					await sleep(300)
					notifTag.remove()
					}
