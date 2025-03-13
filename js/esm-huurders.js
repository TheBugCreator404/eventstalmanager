/*document.addEventListener('DOMContentLoaded', function(){
    // Stel dat je een link hebt met de class 'load-box-data'
    document.querySelectorAll('.load-box-data').forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Voorkom dat de link de pagina herlaadt
            e.preventDefault();
            
            // Haal de GET parameters op (bijvoorbeeld via data-attributen)
            var stal = this.getAttribute('data-stal');
            var box = this.getAttribute('data-box');
            
            // Bouw de AJAX URL
            var ajaxUrl = esm_vars.ajaxUrl; // zorg dat dit via wp_localize_script wordt meegegeven
            var url = ajaxUrl + '?action=esm_get_box_data&stal=' + encodeURIComponent(stal) + '&box=' + encodeURIComponent(box);
            
            // Voer de AJAX-call uit
            fetch(url)
              .then(response => response.json())
              .then(data => {
                  if (data.success) {
                      // Verwerk de data: vervang de inhoud van een container
                      document.getElementById('esm-huurders-content').innerHTML = data.data.html;
                  } else {
                      document.getElementById('esm-huurders-content').innerHTML = '<p>' + data.data + '</p>';
                  }
              })
              .catch(error => console.error('AJAX-fout:', error));
        });
    });
});





document.addEventListener('DOMContentLoaded', function(){
    var params = new URLSearchParams(window.location.search);
    var stal = params.get('stal');
    var box = params.get('box');
    if (stal && box) {
         var url = esm_vars.ajaxUrl + '?action=esm_get_box_data&stal=' + encodeURIComponent(stal) + '&box=' + encodeURIComponent(box);
         fetch(url)
         .then(response => response.json())
         .then(data => {
              if(data.success) {
                  document.getElementById('esm-huurders-content').innerHTML = data.data.html;
              } else {
                  document.getElementById('esm-huurders-content').innerHTML = '<p>' + data.data + '</p>';
              }
         })
         .catch(error => {
              document.getElementById('esm-huurders-content').innerHTML = '<p>Fout: ' + error + '</p>';
         });
    } else {
         document.getElementById('esm-huurders-content').innerHTML = '<p>Fout: Geen stal of box parameter gevonden in de URL.</p>';
    }
});
