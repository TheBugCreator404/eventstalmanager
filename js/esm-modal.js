// Event delegation voor klikken op boxen
document.addEventListener('click', function(e) {
  // Controleer of een element met de class "esm-box" is aangeklikt
  var boxElement = e.target.closest('.esm-box');
  if (boxElement) {
      // Lees de data-attributen uit
      var stalgang = boxElement.getAttribute('data-stalgang');
      var boxnummer = boxElement.getAttribute('data-boxnummer');
      console.log("Box clicked:", stalgang, boxnummer);
      
      // Sla de waarden op in globale variabelen
      window.esm_modal_stalgang = stalgang;
      window.esm_modal_boxnummer = boxnummer;
      
      // Vul de modal met basisinformatie
      var modalBody = document.getElementById('esm-modal-body');
      if (modalBody) {
          modalBody.innerHTML = 
              '<h2>Box ' + boxnummer + ' (Stalgang ' + stalgang + ')</h2>' +
              '<p>Hier komen de details van de box.</p>' +
              '<button id="esm-change-status-btn">Status wijzigen</button>';
      }
      
      // Toon de modal
      var modal = document.getElementById('esm-modal');
      if (modal) {
          modal.style.display = 'block';
      }
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

