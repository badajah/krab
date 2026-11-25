<?php
/* ==========================================================================
   KRAB — includes/entete.php
   --------------------------------------------------------------------------
   Début commun de toutes les pages : <head>, en-tête et menu.

   AVANT d'inclure ce fichier, la page définit deux variables :

       $titre_page  = 'Accueil';   // texte de l'onglet du navigateur
       $page_active = 'accueil';   // lien du menu à mettre en évidence

   Puis :   require __DIR__ . '/includes/entete.php';
   ========================================================================== */

// Valeurs par défaut si la page ne les a pas définies
$titre_page  = $titre_page  ?? '';
$page_active = $page_active ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre_page !== '' ? $titre_page . ' — ' . SITE_NOM : SITE_NOM) ?></title>
    <meta name="description" content="<?= e(SITE_DESCRIPTION) ?>">

    <!-- Feuilles de style : l'ordre est important (variables en premier) -->
    <link rel="stylesheet" href="<?= e(url('assets/css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/composants.css')) ?>">

    <!-- JavaScript : "defer" = chargé après l'affichage de la page -->
    <script src="<?= e(url('assets/js/menu.js')) ?>" defer></script>
</head>
<body>
    <!-- Lien caché, visible au clavier : permet de sauter le menu -->
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>

    <div class="page">
        <header class="entete">
            <div class="conteneur entete-interieur">
                <a class="logo" href="<?= e(url()) ?>"><?= e(SITE_NOM) ?></a>

                <!-- Bouton visible seulement sur tablette et téléphone -->
                <button class="bouton-menu" type="button"
                        aria-controls="navigation-principale" aria-expanded="false">
                    Menu
                </button>

                <nav class="navigation" id="navigation-principale" aria-label="Menu principal">
                    <ul>
                        <li><a href="<?= e(url()) ?>"<?= lien_actif('accueil', $page_active) ?>>Accueil</a></li>
                        <!-- Les liens des modules (Membres, Missions, Agenda...)
                             seront ajoutés ici au fur et à mesure. -->

                        <?php if (est_connecte()): ?>
                            <!-- Liens pour un membre connecté -->
                            <li><a href="<?= e(url('modules/listings/index.php')) ?>"<?= lien_actif('missions', $page_active) ?>>Missions</a></li>
                            <li><a href="<?= e(url('modules/members/annuaire.php')) ?>"<?= lien_actif('membres', $page_active) ?>>Membres</a></li>
                            <li><a href="<?= e(url('modules/members/profil.php')) ?>"<?= lien_actif('mon-profil', $page_active) ?>>Mon profil</a></li>
                            <li><a href="<?= e(url('modules/members/deconnexion.php')) ?>"<?= lien_actif('deconnexion', $page_active) ?>>Se déconnecter</a></li>
                        <?php else: ?>
                            <!-- Liens pour un visiteur -->
                            <li><a href="<?= e(url('modules/members/connexion.php')) ?>"<?= lien_actif('connexion', $page_active) ?>>Connexion</a></li>
                            <li><a href="<?= e(url('modules/members/inscription.php')) ?>"<?= lien_actif('inscription', $page_active) ?>>Inscription</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </header>

        <main id="contenu" class="contenu-principal">
            <div class="conteneur">

                <?php // Messages en attente (ex. « Bienvenue ! » après l'inscription) ?>
                <?php foreach (lire_messages_flash() as $message): ?>
                    <div class="message message-<?= e($message['type']) ?>" role="status">
                        <?= e($message['texte']) ?>
                    </div>
                <?php endforeach; ?>
