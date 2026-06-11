function pswdRequirementsCheck() {
		// check new psswd validity
		const box = Box('password')
		const error = box.error('password')
		const value = Input('pswd').value
		
		const checkList = [
					{'regex': /.{6,}/, selector: '.length'},
					{'regex': /[a-z]/, selector: '.minuscule'},
					{'regex': /[A-Z]/, selector: '.majuscule'},
					{'regex': /[0-9]/, selector: '.number'},
					{'regex': /[^\sa-zA-Z0-9]/, selector: '.special'},
					]
		
		// loop through checks
		checkList.forEach(item => {
				tag = document.querySelector(`.check-item${item.selector}`)
				if (value.match(item.regex) === null) {
					tag.set('invalid')
					tag.unset('valid')
				} else {
					tag.unset('invalid')
					tag.set('valid')
				}
		})
		
		box.child('.check-item[invalid]') !== null ? error.show():error.hide()
		pswdCheck()
}

// Password match check
function pswdCheck() {
		const error = Box('password-confirm').error('confirmation')
		const value = Input('pswd').value
		const confirmation = Input('pswd-confirm').value
		
		value !== confirmation && confirmation !== '' ? error.show():error.hide()
		submitAllower()
}