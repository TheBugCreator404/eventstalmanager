document.addEventListener('DOMContentLoaded', function(){
  var modal = document.getElementById("esm-modal");
  var closeBtn = document.querySelector(".esm-close");

  // Voeg een click-event toe aan alle elementen met de class .esm-box
  document.addEventListener('click', function(e) {
    // Zoek naar een ouder-element met de class .esm-box van het geklikte element
    var box = e.target.closest('.esm-box');
    if (box) {
        // Voorkom dat andere handlers de click verder verwerken (optioneel)
        // e.preventDefault();
        
        // Haal de benodigde data op
        var stalgang = box.getAttribute('data-stalgang');
        var boxnummer = box.getAttribute('data-boxnummer');
        
        // Zorg dat je de pop-up elementen hebt
        var modal = document.getElementById('esm-modal');
        var modalBody = document.getElementById('esm-modal-body');
        
        // Vul de pop-up met dynamische inhoud, bijvoorbeeld:
        modalBody.innerHTML = 
            '<h2>Box ' + boxnummer + ' (Stalgang ' + stalgang + ')</h2>' +
            '<p>Hier komen de details van de box.</p>' +
            '<button id="esm-change-status-btn">Status wijzigen</button>';
        
        // Toon de pop-up
        modal.style.display = "block";
    }
});

  // Gebruik event delegation voor de "Status wijzigen" knop
  document.addEventListener('click', function(e) {
    if ( e.target && e.target.id === 'esm-change-status-btn' ) {
        // Zoek of er al een update container is
        var updateContainer = document.getElementById("esm-update-container");
        if (!updateContainer) {
            // Als die niet bestaat, maak hem aan en voeg hem toe aan de modal body
            updateContainer = document.createElement('div');
            updateContainer.id = "esm-update-container";
            document.getElementById("esm-modal-body").appendChild(updateContainer);
        }
        // Vervang de inhoud van de update container met de verse CF7-formulieroutput
        updateContainer.innerHTML = esm_modal_vars.cf7UpdateForm;
        
        // Direct verbergen: zoek de response output en zet display op none
        var responseOutput = updateContainer.querySelector('.wpcf7-response-output');
        if(responseOutput){
            responseOutput.style.display = 'none';
        }
        
        // Na een korte vertraging update de hidden fields met de juiste waarden
        setTimeout(function(){
            var stalInput = updateContainer.querySelector('input[name="stal"]');
            var boxInput = updateContainer.querySelector('input[name="box"]');
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
