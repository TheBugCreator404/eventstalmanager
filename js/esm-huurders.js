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
