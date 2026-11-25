/* ==========================================================================
   KRAB — assets/js/agenda.js
   --------------------------------------------------------------------------
   Filtres de l'agenda : dès qu'on coche ou décoche une case, le formulaire
   est envoyé et l'agenda se met à jour. Le bouton « Afficher » devient
   inutile : on le cache en ajoutant la classe "agenda-filtres-auto"
   (c'est agenda.css qui le masque).

   Sans JavaScript, le bouton « Afficher » reste visible et fonctionne.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    var formulaire = document.getElementById('agenda-filtres');
    if (!formulaire) {
        return;
    }

    formulaire.classList.add('agenda-filtres-auto');

    formulaire.querySelectorAll('input[type="checkbox"]').forEach(function (caseACocher) {
        caseACocher.addEventListener('change', function () {
            formulaire.submit();
        });
    });
});
