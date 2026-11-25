<?php
/* ==========================================================================
   KRAB — includes/fonctions.php
   --------------------------------------------------------------------------
   Petites fonctions pratiques utilisées dans tout le site.
   ========================================================================== */


/**
 * Construit l'adresse complète d'une page ou d'un fichier du site.
 *
 * Exemple :   url('assets/css/base.css')   →   /krab/assets/css/base.css
 *
 * Ainsi, si le site change d'emplacement, seul SITE_CHEMIN
 * (dans config.php) est à modifier.
 */
function url(string $chemin = ''): string
{
    return SITE_CHEMIN . '/' . ltrim($chemin, '/');
}


/**
 * Envoie le visiteur vers une autre page du site, puis arrête la page actuelle.
 *
 * Exemple :   rediriger('modules/members/connexion.php');
 */
function rediriger(string $chemin): void
{
    header('Location: ' . url($chemin));
    exit;
}


/**
 * Prépare un message à afficher sur la PROCHAINE page
 * (par exemple après une redirection : « Bienvenue ! »).
 *
 * $type : 'succes', 'erreur', 'alerte' ou 'info'
 *         (correspond aux classes message-succes, message-erreur... de composants.css)
 */
function message_flash(string $type, string $texte): void
{
    $_SESSION['messages_flash'][] = ['type' => $type, 'texte' => $texte];
}


/**
 * Récupère les messages en attente et les efface
 * (ils ne s'affichent qu'une seule fois).
 */
function lire_messages_flash(): array
{
    $messages = $_SESSION['messages_flash'] ?? [];
    unset($_SESSION['messages_flash']);
    return $messages;
}


/**
 * Renvoie ' class="actif"' si le lien correspond à la page affichée,
 * sinon rien. Sert à mettre en évidence la page courante dans le menu.
 *
 * Exemple :   <a href="..."<?= lien_actif('accueil', $page_active) ?>>Accueil</a>
 */
function lien_actif(string $nom_lien, string $page_active): string
{
    return $nom_lien === $page_active ? ' class="actif" aria-current="page"' : '';
}
