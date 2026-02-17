function paswdRequirementsCheck() {
			// check new psswd validity
				ptag = Box('password').error('password');
				pswd = Input('pswd').value;
				
				checkList = [
							{'regex': /.{6,}/, selector: '.length'},
							{'regex': /[a-z]/, selector: '.minuscule'},
							{'regex': /[A-Z]/, selector: '.majuscule'},
							{'regex': /[0-9]/, selector: '.number'},
							{'regex': /[^\sa-zA-Z0-9]/, selector: '.special'},
							]
				
				// loop through checks
				checkList.forEach(item => {
						tag = document.querySelector(`.check-item${item.selector}`);
						if (pswd.match(item.regex) === null) {
							tag.set('invalid');
							tag.unset('valid');
						} else {
							tag.unset('invalid');
							tag.set('valid');
						}
				})
				
				if (document.querySelector('.check-item[invalid]') !== null) {
					ptag.show() 
				} else {
					ptag.hide()
				}

				psswdCheck()
		}

// Password match check
function psswdCheck() {
		pswd = Input('pswd').value;
		pswdConf = Input('pswd-confirm').value;
		
		if (pswd !== pswdConf  && pswdConf !== '') {
			Box('password-confirm').error('confirmation').show();
		} else {
			Box('password-confirm').error('confirmation').hide();
		}
		submitAllower()
}

function compareWithOldValue(e) {
			errorTag = e.target.findParent('.box').error();
			if (e.target.value !== e.target.getAttribute('oldvalue')) {
				e.target.unset('invalid');
				errorTag.hide();
			} else {
				e.target.set('invalid','');
				errorTag.show();
			}
		}


async function checkInput(e, type) {
			inputtag = e.target;

			if (!inputtag.checkValidity()) {
				return
			}
			
			// contents
			value = e.target.value.urlForm();

			// DOM elements
			spin = inputtag.findParent('.input-container').spinner();
			error = inputtag.findParent('.box').error();
			
			inputtag.value = value;
			inputtag.setAttribute('oldvalue',value);
			inputtag.unset('valid');
			error.hide();
			spin.show();
			await sleep(250);
			
			response = await fetch(`/auth/login?check=availability&type=${type}&content=${value}`);
			data = await response.json(); // dict parsed response data
			
			if (!response.ok) {
				inputtag.set('invalid');
				spin.hide();
				error.show();
			} else {
				inputtag.unset('invalid');
				inputtag.set('valid');

				inputtag.value = data.value;
				inputtag.unset('oldvalue');

				spin.hide();
				error.hide();
			}
			submitAllower()
		}

window.onload = loginConfig
function loginConfig() {

}