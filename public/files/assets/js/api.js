async function syncerAPI(e, api) {
        notify(`Synchronisation depuis ${api}`, 'orange')
		e.target.findParent('.icon').show();
		e.target.findParent('.icon').setAttribute('title',`Fetching data from ${api}`);

		jsonData = JSON.stringify({'csrf':csrfToken})

		response = await fetch(`/api/${api.toLowerCase()}/fetch`, {
								method: "POST",
								body: jsonData,
								headers: { 'Content-Type': 'application/json' }
								})
		
		e.target.findParent('.icon').hide();
		e.target.findParent('.icon').setAttribute('title','Synchroniser les données');
		
		if (!response.ok) {
			json = await response.json();

			notify('Erreur lors de la syncronisation : '+json.error, 'red')
            return false
		}

        return true
}