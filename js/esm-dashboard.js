document.addEventListener('DOMContentLoaded', function(){
    // Functie om de dashboard-data via AJAX op te halen en de container bij te werken.
    function refreshDashboard() {
        var url = esm_dashboard_vars.ajaxUrl + '?action=esm_get_dashboard_data';
        fetch(url)
        .then(response => response.json())
        .then(data => {
             if(data.success) {
                 document.getElementById('esm-dashboard-content').innerHTML = data.data.html;
             } else {
                 console.error("Dashboard refresh error:", data.data);
             }
        })
        .catch(error => {
             console.error("Fetch error:", error);
        });
    }
    
    // Voer de eerste refresh uit bij het laden.
    refreshDashboard();
    
    // Stel het interval in (bijvoorbeeld 30000 ms of wat er in de settings is ingesteld).
    setInterval(refreshDashboard, parseInt(esm_dashboard_vars.refreshInterval, 10));
});
