<?php
/* ==========================================================================
   KRAB — includes/config.php
   --------------------------------------------------------------------------
   Réglages généraux du site.

   C'est le SEUL fichier à modifier quand le site change d'ordinateur
   ou d'hébergeur (par exemple : passage de MAMP à un vrai serveur).

   Les valeurs ci-dessous sont celles de MAMP par défaut sur Mac.
   ========================================================================== */


/* --------------------------------------------------------------------------
   1. BASE DE DONNÉES (MySQL)
   Dans MAMP, ces informations sont visibles sur la page d'accueil de MAMP
   (bouton "WebStart" ou "Open WebStart page").
   -------------------------------------------------------------------------- */

define('BDD_HOTE', 'localhost');     // adresse du serveur MySQL. Avec 'localhost',
                                     // PHP passe par le socket réglé dans MAMP
define('BDD_PORT', 8889);            // port MySQL de MAMP (8889 par défaut)
define('BDD_NOM', 'krab');           // nom de la base de données

// "Socket" de MySQL : un passage direct vers MySQL sur le même ordinateur.
// MAMP le place à cet endroit. S'il existe, le site l'utilise en priorité
// (c'est ce que fait phpMyAdmin) ; sinon il passe par BDD_HOTE et BDD_PORT.
// Chez un hébergeur, laisser tel quel : le fichier n'existera pas.
define('BDD_SOCKET', '/Applications/MAMP/tmp/mysql/mysql.sock');

define('BDD_UTILISATEUR', 'root');   // utilisateur MySQL de MAMP
define('BDD_MOT_DE_PASSE', 'root');  // mot de passe MySQL de MAMP
                                     // ⚠ à changer sur un vrai serveur !


/* --------------------------------------------------------------------------
   2. ADRESSE DU SITE
   Chemin du site dans le navigateur, APRÈS l'adresse du serveur.

   Avec MAMP, si le dossier du site est  /Applications/MAMP/htdocs/krab
   le site s'ouvre à l'adresse  http://localhost:8888/krab/
   → le chemin est donc '/krab'.

   Si le site est à la racine d'un hébergeur, mettre une chaîne vide : ''
   -------------------------------------------------------------------------- */

define('SITE_CHEMIN', '/krab');


/* --------------------------------------------------------------------------
   3. INFORMATIONS GÉNÉRALES
   -------------------------------------------------------------------------- */

define('SITE_NOM', 'KRAB');
define('SITE_DESCRIPTION', 'Plateforme communautaire d\'entraide professionnelle en Suisse');

// Fuseau horaire utilisé pour toutes les dates (agenda, messages...)
date_default_timezone_set('Europe/Zurich');


/* --------------------------------------------------------------------------
   4. MODE DÉVELOPPEMENT
   true  = les erreurs PHP s'affichent à l'écran (pratique pour corriger)
   false = les erreurs sont cachées aux visiteurs (OBLIGATOIRE en ligne)
   -------------------------------------------------------------------------- */

define('MODE_DEVELOPPEMENT', true);
