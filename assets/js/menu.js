/* ==========================================================================
   KRAB — assets/js/menu.js
   --------------------------------------------------------------------------
   Ouvre et ferme le menu principal sur tablette et téléphone.

   Principe (règle du projet) : le JavaScript ne change JAMAIS le style
   directement. Il ajoute ou retire la classe "menu-ouvert" ;
   c'est layout.css qui décide de l'apparence.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    var bouton = document.querySelector('.bouton-menu');
    var menu = document.getElementById('navigation-principale');

    // Si la page n'a pas de menu, on ne fait rien
    if (!bouton || !menu) {
        return;
    }

    bouton.addEventListener('click', function () {
        // toggle : ajoute la classe si elle est absente, la retire sinon
        var estOuvert = menu.classList.toggle('menu-ouvert');

        // Indique l'état du menu aux lecteurs d'écran (accessibilité)
        bouton.setAttribute('aria-expanded', estOuvert ? 'true' : 'false');
    });
});
