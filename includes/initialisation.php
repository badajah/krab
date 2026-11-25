<?php
/* ==========================================================================
   KRAB — includes/initialisation.php
   --------------------------------------------------------------------------
   À inclure TOUT EN HAUT de chaque page PHP du site :

       require __DIR__ . '/includes/initialisation.php';

   (adapter le chemin selon l'emplacement de la page, par exemple
    __DIR__ . '/../../includes/initialisation.php' depuis un module)

   Ce fichier charge la configuration, les outils communs,
   et démarre la session.
   ========================================================================== */

require_once __DIR__ . '/config.php';

// Affichage des erreurs selon le mode choisi dans config.php
if (MODE_DEVELOPPEMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');   // les erreurs restent dans le journal du serveur
}

require_once __DIR__ . '/securite.php';
require_once __DIR__ . '/base-de-donnees.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/authentification.php';
require_once __DIR__ . '/membres.php';
require_once __DIR__ . '/groupes.php';

demarrer_session();
