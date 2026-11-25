/* ==========================================================================
   KRAB — assets/js/confirmation.js
   --------------------------------------------------------------------------
   Demande une confirmation avant d'envoyer certains formulaires.

   Utilisation : ajouter l'attribut data-confirmation au formulaire :
       <form method="post" data-confirmation="Inviter Nina sur cette mission ?">

   Si le texte est vide, aucune confirmation n'est demandée.
   ========================================================================== */

document.addEventListener('submit', function (evenement) {

    var formulaire = evenement.target;
    var question = formulaire.getAttribute('data-confirmation');

    // window.confirm affiche « OK / Annuler » ; « Annuler » bloque l'envoi
    if (question && !window.confirm(question)) {
        evenement.preventDefault();
    }
});
