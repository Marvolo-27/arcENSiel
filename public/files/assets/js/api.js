async function syncerAPI(e, api) {
		const icon = e.target

        notify("Synchronisation en cours",  `Les données sont en cours de synchronisation depuis le serveur ${api}.`, 'orange')
		
		icon.show()
		icon.setAttribute('title',`Récupération des données depuis ${api}`)

		const jsonData = JSON.stringify({'csrf':csrfToken})
		const response = await fetch(`/api/${api.toLowerCase()}/fetch`, {
								method: "POST",
								body: jsonData,
								headers: { 'Content-Type': 'application/json' }
								})
		
		icon.hide()
		icon.setAttribute('title','Synchroniser les données')
		
		if (!response.ok) {
			const json = await response.json()

			notify("Erreur lors de la syncronisation", json.error, 'red')
		}

        return response.ok
}