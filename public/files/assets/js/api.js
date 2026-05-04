async function syncerAPI(e, api) {
        notify(`Synchronisation depuis ${api}`, 'orange')
		icon = e.target.findParent('.icon')
		icon.show();
		icon.setAttribute('title',`Récupération des données depuis ${api}`);

		jsonData = JSON.stringify({'csrf':csrfToken})

		response = await fetch(`/api/${api.toLowerCase()}/fetch`, {
								method: "POST",
								body: jsonData,
								headers: { 'Content-Type': 'application/json' }
								})
		
		icon.hide();
		icon.setAttribute('title','Synchroniser les données');
		
		if (!response.ok) {
			json = await response.json();

			notify('Erreur lors de la syncronisation : '+json.error, 'red')
            return false
		}

        return true
}