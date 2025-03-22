// Event delegation voor klikken op boxen
document.addEventListener('click', function(e) {
  // Gebruik event delegation: als een element met class 'esm-box' wordt geklikt
  var boxElement = e.target.closest('.esm-box');
  if (boxElement) {
      // Haal de waarden uit de data-attributen (als fallback, maar deze gaan we via AJAX ophalen)
      var stalgang = boxElement.getAttribute('data-stalgang');
      var boxnummer = boxElement.getAttribute('data-boxnummer');
      console.log("Box clicked: ", stalgang, boxnummer);
      
      window.esm_modal_stalgang = stalgang;
      window.esm_modal_boxnummer = boxnummer;
      
      // Bouw de AJAX URL op
      var ajaxUrl = esm_vars.ajaxUrl; // Deze variabele moet via wp_localize_script worden meegegeven
      var url = ajaxUrl + '?action=esm_get_box_data&stal=' + encodeURIComponent(stalgang) + '&box=' + encodeURIComponent(boxnummer);
      console.log("AJAX URL:", url);
      
      // Doe de AJAX-aanroep om de meest actuele gegevens op te halen
      fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var info = data.data;
                // Bouw de HTML voor de pop-up op basis van de response
                var modalHtml = `
                    <h2>Box ${info.boxnummer} (Stalgang ${info.stalgang})</h2>
                    <p><strong>Status:</strong> ${info.current_status}</p>
                    <p><strong>Vorige status:</strong> ${info.previous_status}</p>
                    <p><strong>Laatste wijziging:</strong> ${info.last_modified}</p>
                    <p><strong>Gewijzigd door:</strong> ${info.modified_by}</p>
                    <button id="esm-change-status-btn">Status wijzigen</button>
                `;
                var modalBody = document.getElementById('esm-modal-body');
                if (modalBody) {
                    modalBody.innerHTML = modalHtml;
                }
                
                // Toon de modal
                var modal = document.getElementById('esm-modal');
                if (modal) {
                    modal.style.display = 'block';
                }
                
                // Afhankelijk van de toegestane acties kun je het updateformulier inladen
                // Bijvoorbeeld: als aanmelden toegestaan is, laad dan de CF7-updateform (als je dat via modal_vars hebt doorgegeven)
                // Of je kunt hier extra logica toevoegen om het formulier te tonen
            } else {
                console.error("AJAX error:", data.data);
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
        });
  }
});
// Event delegation voor de sluit-knop en buiten de modal klikken
document.addEventListener('click', function(e) {
  // Als het element met class "esm-close" wordt aangeklikt of als er buiten de modal wordt geklikt:
  if (e.target.matches('.esm-close') || e.target.matches('.esm-modal')) {
      var modal = document.getElementById('esm-modal');
      if (modal) {
          modal.style.display = 'none';
      }
  }
});

// Event delegation voor de "Status wijzigen" knop in de modal
document.addEventListener('click', function(e) {
  if (e.target && e.target.id === 'esm-change-status-btn') {
      // Zoek of er al een update-container bestaat en verwijder die
      var updateContainer = document.getElementById('esm-update-form');
      if (updateContainer) {
          updateContainer.remove();
      }
      // Voeg het CF7-updateformulier toe, gebruikmakend van de via wp_localize_script doorgegeven variabele
      var modalBody = document.getElementById('esm-modal-body');
      if (modalBody && typeof esm_modal_vars !== 'undefined' && esm_modal_vars.cf7UpdateForm) {
          updateContainer = document.createElement('div');
          updateContainer.id = 'esm-update-form';
          updateContainer.innerHTML = esm_modal_vars.cf7UpdateForm;
          modalBody.innerHTML += updateContainer.outerHTML;
          // Na een korte vertraging, update de hidden fields in het formulier
          setTimeout(function() {
              var form = document.getElementById('esm-update-form');
              if (form) {
                  var stalInput = form.querySelector('input[name="stal"]');
                  var boxInput = form.querySelector('input[name="box"]');
                  console.log("Update hidden fields with:", window.esm_modal_stalgang, window.esm_modal_boxnummer);
                  if (stalInput) stalInput.value = window.esm_modal_stalgang;
                  if (boxInput) boxInput.value = window.esm_modal_boxnummer;
              }
          }, 50);
      }
  }
});

  
  // Als er validatiefouten of spamfouten zijn, maak de response-output zichtbaar zodat de foutmeldingen zichtbaar worden
  document.addEventListener('wpcf7invalid', function(event) {
     var form = event.target;
     if(form.closest('#esm-update-container')) {
         var responseOutput = form.querySelector('.wpcf7-response-output');
         if(responseOutput){
            responseOutput.style.display = 'block';
         }
     }
  });
  document.addEventListener('wpcf7spam', function(event) {
     var form = event.target;
     if(form.closest('#esm-update-container')) {
         var responseOutput = form.querySelector('.wpcf7-response-output');
         if(responseOutput){
            responseOutput.style.display = 'block';
         }
     }
  });
  
  document.addEventListener('wpcf7invalid', function(event) {
    // Controleer of het updateformulier betrokken is
    var form = event.target;
    if ( form.closest('#esm-update-container') ) {
        // Voorkom dat de modal sluit (optioneel kun je hier debug info loggen)
        console.log('Validatiefout: modal blijft open');
        // Je zou hier eventueel de modal opnieuw zichtbaar kunnen maken:
        modal.style.display = "block";
    }
});


  closeBtn.onclick = function() {
    modal.style.display = "none";
  };

  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  };

