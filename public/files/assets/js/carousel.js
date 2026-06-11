function carouselNext() {
		const current = Section('carousel-inputs').input(':checked')
		const next = current.nextElementSibling ?? current.parentNode.firstElementChild 
		
		current.checked = false
		next.checked = true
	}

function carouselPrev() {
		const current = Section('carousel-inputs').input(':checked')
		const next  = current.previousElementSibling ?? current.parentNode.lastElementChild 
		
		current.checked = false
		next.checked = true
	}

async function carouselShifter() {
		while (true) {
			await sleep(4000)
			carouselNext()
		}
	}

function carouselOpen(i) {
		carouselReset(i)
		Section('carousel-wrap').style.display = 'flex';
	}

function carouselReset(i) {
		document.querySelectorAll('.carousel-button').forEach(item => Input(item.getAttribute('for')).checked = parseInt(item.id.slice(-1)) === i)
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
        const wrap = document.querySelector(selector)
        wrap.innerHTML = `
                <div class='icon chevron prev hoverable very-large inline invertable dynamic'></div>
				<div class='carousel'></div>
				<div class='icon chevron next hoverable very-large inline invertable dynamic'></div>
                `
        wrap.classList.add('carousel-wrap')
        wrap.icon('prev').onclick = carouselPrev
        wrap.icon('next').onclick = carouselNext
        
        // Config container
        const container = wrap.child('.carousel')
        Array.from(['imgs', 'inputs', 'lbls']).forEach(category => {
            div = document.createElement('div')
            div.classList.toggle(`carousel-${category}`)
            container.append(div)
        })

        // Get sub-containers
        const inputs = container.section('carousel-inputs')
		const labels = container.section('carousel-lbls')
		const images = container.section('carousel-imgs')
		
        // Procedural generation
        let i = 1
		document.querySelector('head').child('style').textContent += '\n\n/* Auto-generated */'
		data.forEach(item => {
				inp = document.createElement('input')
				if (i == 1) inp.checked = true
				inp.type = 'radio'
				inp.id = `carousel-item-${i}`
				inputs.appendChild(inp)
				
				lbl = document.createElement('label')
				lbl.setAttribute('for',`carousel-item-${i}`)
				lbl.id = `carousel-button-${i}`
				lbl.classList.toggle('carousel-button')
				lbl.onclick = () => carouselReset(i)
				labels.appendChild(lbl)
				
				imgWrap = document.createElement('div')
				imgWrap.classList.toggle('carousel-img-wrap')
				
				img = document.createElement('img')
				img.src = item.src;
				item.url ? img.dataset.url = item.url:null;
				if (item.url) img.onclick = e => window.location.href = e.target.dataset.url
				imgWrap.appendChild(img)
				images.appendChild(imgWrap)

				document.querySelector('head').child('style').textContent += `\n.carousel:has(#carousel-item-${i}:checked) {.carousel-imgs {transform: translateX(calc(-${i-1}*(100%\/var(--nb))))}  #carousel-button-${i} {background-color: #fff}}`;

				i++;
                })
        
        }