function paswdRequirementsCheck() {
			// check new psswd validity
				ptag = document.querySelector('.box.password .error.password');
				pswd = document.querySelector('#pswd').value;
				
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
							tag.setAttribute('invalid','');
							tag.removeAttribute('valid');
						} else {
							tag.removeAttribute('invalid');
							tag.setAttribute('valid','');
						}
				})
				
				if (document.querySelector('.check-item[invalid]') !== null) {
					ptag.setAttribute('active',"")
				} else {
					ptag.removeAttribute('active')
				}

			// check new passwds equality
				pswd = document.querySelector('#pswd').value;
				pswdConf = document.querySelector('#pswd-bis').value;
				
				if (pswd !== pswdConf && pswdConf !== '') {
					document.querySelector('.box.password-confirm .error.confirmation').setAttribute('active','');
				} else {
					document.querySelector('.box.password-confirm .error.confirmation').removeAttribute('active');
				}
			
				submitAllower()
		}

// Password match check
function psswdCheck() {
		pswd = document.querySelector('#pswd').value;
		pswdConf = document.querySelector('#pswd-bis').value;
		
		if (pswd !== pswdConf  && pswdConf !== '') {
			document.querySelector('.box.password-confirm .error.confirmation').setAttribute('active','');
		} else {
			document.querySelector('.box.password-confirm .error.confirmation').removeAttribute('active');
		}
		submitAllower()
}

function compareWithOldValue(e) {
											errorTag = e.target.findParent('.box').querySelector('.error');
											if (e.target.value !== e.target.getAttribute('oldvalue')) {
												e.target.removeAttribute('invalid');
												errorTag.removeAttribute('active');
											} else {
												e.target.setAttribute('invalid','');
												errorTag.setAttribute('active','');
											}
										}


async function checkInput(e, type) {
					inputtag = e.target;

					if (!inputtag.checkValidity()) {
						return
					}

					spin = inputtag.findParent('.input-container').querySelector('.spinner');
					value = e.target.value.urlForm();
					errorTag = inputtag.findParent('.box').querySelector('.error');
					
					inputtag.value = value;
					inputtag.setAttribute('oldvalue',value);
					inputtag.removeAttribute('valid');
					errorTag.removeAttribute('active');
					spin.setAttribute('active','');
					await sleep(250);
					
					response = await fetch(`/login/login.php?check=availability&type=${type}&content=${value}`);
					data = await response.json(); // dict parsed response data
					
					if (!response.ok) {
						inputtag.setAttribute('invalid','');
						spin.removeAttribute('active');
						errorTag.setAttribute('active','');
						// console.log(data.error)
					} else {
						inputtag.removeAttribute('invalid','');
						inputtag.value = data.value;
						inputtag.removeAttribute('oldvalue');
						spin.removeAttribute('active');
						inputtag.setAttribute('valid','');
						errorTag.removeAttribute('active');
					}
					submitAllower()
				}