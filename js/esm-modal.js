document.addEventListener('DOMContentLoaded', function(){
  var modal = document.getElementById("esm-modal");
  var closeBtn = document.querySelector(".esm-close");

  // Voeg een click-event toe aan alle elementen met de class .esm-box
  document.querySelectorAll('.esm-box').forEach(function(box) {
    box.addEventListener('click', function(){
        // Lees de data-attributen uit de aangeklikte box
        var stalgang = this.getAttribute('data-stalgang');
        var boxnummer = this.getAttribute('data-boxnummer');
        console.log("Geselecteerde box:", stalgang, boxnummer);
        window.esm_modal_stalgang = stalgang;
        window.esm_modal_boxnummer = boxnummer;

        // Open de modal en vul deze met de basisgegevens (voorbeeld)
        var modal = document.getElementById("esm-modal");
        document.getElementById("esm-modal-body").innerHTML =
            '<h2>Box ' + boxnummer + ' (Stalgang ' + stalgang + ')</h2>' +
            '<p>Hier komen de details van de box.</p>' +
            '<button id="esm-change-status-btn">Status wijzigen</button>';
        modal.style.display = "block";
    });
});


  // Gebruik event delegation voor de "Status wijzigen" knop
  document.addEventListener('click', function(e) {
    if ( e.target && e.target.id === 'esm-change-status-btn' ) {
        // Zoek of er al een update container is
        var updateContainer = document.getElementById("esm-update-container");
        if (!updateContainer) {
            updateContainer = document.createElement('div');
            updateContainer.id = "esm-update-container";
            document.getElementById("esm-modal-body").appendChild(updateContainer);
        }
        // Voeg het CF7 updateformulier toe
        updateContainer.innerHTML = esm_modal_vars.cf7UpdateForm;
        
        // Verberg direct de CF7 response-output
        var responseOutput = updateContainer.querySelector('.wpcf7-response-output');
        if (responseOutput) {
            responseOutput.style.display = 'none';
        }
        
        // Na een korte vertraging, update de hidden fields met de globale variabelen
        setTimeout(function(){
            var stalInput = updateContainer.querySelector('input[name="stal"]');
            var boxInput = updateContainer.querySelector('input[name="box"]');
            console.log("Update hidden fields met:", window.esm_modal_stalgang, window.esm_modal_boxnummer);
            if (stalInput) stalInput.value = window.esm_modal_stalgang;
            if (boxInput) boxInput.value = window.esm_modal_boxnummer;
        }, 50);
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
});
