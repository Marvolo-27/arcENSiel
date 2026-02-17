function carouselNext() {
		curr = document.querySelector('.carousel-inputs').querySelector('input:checked')
		next = curr.nextElementSibling === null ? curr.parentNode.firstElementChild : curr.nextElementSibling;
		next.checked = true;
		curr.checked = false;
	}

function carouselPrev() {
		curr = document.querySelector('.carousel-inputs').querySelector('input:checked')
		next = curr.previousElementSibling === null ? curr.parentNode.lastElementChild : curr.previousElementSibling;
		next.checked = true;
		curr.checked = false;
	}

async function carouselShifter() {
		while (true) {
			await sleep(4000)
			
			carouselNext()
		}
	}

function carouselOpen(i) {
		reset(i);
		document.querySelector('.carousel-wrap').style.display = 'flex';
	}

function carouselReset(i) {
		document.querySelectorAll('.carousel-button').forEach(item => {
				item.checked = parseInt(item.id.slice(-1)) === i
				})
	}

function carouselGenerator(selector, data) {
        /*
        Generate automatically carousel using input data

        data : [
            {
                src: STR - path to image to display in carousel
                url: STR Nullable - URL to fetch on click, if defined
            },
        ]
        */

        // config wrapper
        wrap = document.querySelector(selector)
        wrap.innerHTML = `
                <div class='icon chevron prev hoverable very-large inline invertable dynamic'><span class='img'></span></div>
				<div class='carousel'></div>
				<div class='icon chevron next hoverable very-large inline invertable dynamic'><span class='img'></span></div>
                `
        wrap.className = ''
        wrap.classList.add('carousel-wrap')
        wrap.icon('prev').onclick = carouselPrev
        wrap.icon('next').onclick = carouselNext
        
        // Config container
        container = wrap.child('.carousel')
        Array.from(['imgs', 'inputs', 'lbls']).forEach(category => {
            div = document.createElement('div')
            div.classList.toggle(`carousel-${category}`)
            container.append(div)
        })

        // Get sub-containers
        inps = container.child('.carousel-inputs')
		lbls = container.child('.carousel-lbls')
		imgs = container.child('.carousel-imgs')
		
        // Procedural generation
        i = 1;
		document.querySelector('head').child('style').textContent += '\n\n/* Auto-generated */'
		data.forEach(item => {
				inp = document.createElement('input');
				i == 1 ? inp.checked = true:null;
				inp.type = 'radio';
				inp.id = `carousel-item-${i}`;
				inps.appendChild(inp);
				
				lbl = document.createElement('label');
				lbl.setAttribute('for',`carousel-item-${i}`);
				lbl.id = `carousel-button-${i}`;
				lbl.classList.toggle('carousel-button');
				lbl.onclick = e => reset(i);
				lbls.appendChild(lbl);
				
				imgWrap = document.createElement('div');
				imgWrap.classList.toggle('carousel-img-wrap')
				
				img = document.createElement('img');
				img.src = item.src;
				item.url ? img.dataset.url = item.url:null;
				imgWrap.appendChild(img);
				imgs.appendChild(imgWrap);
				item.url ? img.onclick = e => window.location.href = e.target.dataset.url:null;

				document.querySelector('head').child('style').textContent += `\n.carousel:has(#carousel-item-${i}:checked) {.carousel-imgs {transform: translateX(calc(-${i-1}*(100%\/var(--nb))))}  #carousel-button-${i} {background-color: #fff}}`;

				i++;
                })
        
        }