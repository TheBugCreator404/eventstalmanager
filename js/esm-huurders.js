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
                  var info = data.data;
                  var detailsHtml = '<h2>Box Details</h2>' +
                      '<p><strong>Stalgang:</strong> ' + info.stalgang + '</p>' +
                      '<p><strong>Boxnummer:</strong> ' + info.boxnummer + '</p>' +
                      '<p><strong>Huidige status:</strong> ' + info.current_status + '</p>' +
                      '<p><strong>Vorige status:</strong> ' + info.previous_status + '</p>' +
                      '<p><strong>Laatste wijziging:</strong> ' + info.last_modified + '</p>' +
                      '<p><strong>Gewijzigd door:</strong> ' + info.modified_by + '</p>';
                  document.getElementById('box-details').innerHTML = detailsHtml;
                  
                  // Toon het juiste formulier op basis van de toegestane acties
                  if(info.allowed_aanmelden) {
                      document.getElementById('cf7-aanmelden').style.display = 'block';
                      document.getElementById('cf7-afmelden').style.display = 'none';
                  } else if(info.allowed_afmelden) {
                      document.getElementById('cf7-afmelden').style.display = 'block';
                      document.getElementById('cf7-aanmelden').style.display = 'none';
                  } else {
                      document.getElementById('cf7-aanmelden').style.display = 'none';
                      document.getElementById('cf7-afmelden').style.display = 'none';
                  }
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
