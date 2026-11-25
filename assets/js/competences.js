/* ==========================================================================
   KRAB — assets/js/competences.js
   --------------------------------------------------------------------------
   Limite le nombre de compétences cochées (3) dans le formulaire du profil :
   quand le maximum est atteint, les autres cases deviennent indisponibles.

   C'est un CONFORT pour le membre. La vraie vérification est faite par le
   PHP (modifier-profil.php), car le JavaScript peut être désactivé.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    var groupe = document.getElementById('choix-competences');
    if (!groupe) {
        return;
    }

    var maximum = parseInt(groupe.getAttribute('data-maximum'), 10);
    var cases = groupe.querySelectorAll('input[type="checkbox"]');

    function mettreAJour() {
        var nombreCochees = groupe.querySelectorAll('input[type="checkbox"]:checked').length;

        cases.forEach(function (caseACocher) {
            // Une case non cochée devient indisponible quand le maximum est atteint
            caseACocher.disabled = !caseACocher.checked && nombreCochees >= maximum;
        });
    }

    cases.forEach(function (caseACocher) {
        caseACocher.addEventListener('change', mettreAJour);
    });

    mettreAJour();
});
