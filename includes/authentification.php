<?php
/* ==========================================================================
   KRAB — includes/authentification.php
   --------------------------------------------------------------------------
   Gestion de la connexion des membres.

   Principe : quand un membre se connecte, on enregistre seulement son
   numéro (id) dans la session. À chaque page, on relit ses informations
   dans la base de données.

   Fonctions principales :
     membre_connecte()    → le membre connecté (tableau), ou null
     est_connecte()       → true / false
     exiger_connexion()   → à mettre en haut d'une page réservée aux membres
     exiger_visiteur()    → à mettre en haut d'une page réservée aux visiteurs
     connecter_membre()   → après vérification du mot de passe
     deconnecter_membre() → déconnexion complète
   ========================================================================== */


/**
 * Renvoie les informations du membre connecté, ou null si personne
 * n'est connecté. Le mot de passe n'est jamais renvoyé.
 */
function membre_connecte(): ?array
{
    // "static" : on ne lit la base qu'une fois par page
    static $membre = false;

    if ($membre !== false) {
        return $membre;
    }

    $membre = null;

    if (!empty($_SESSION['membre_id'])) {
        $requete = bdd()->prepare(
            'SELECT id, prenom, nom, email, date_inscription
             FROM membres
             WHERE id = :id'
        );
        $requete->execute(['id' => $_SESSION['membre_id']]);
        $resultat = $requete->fetch();

        if ($resultat) {
            $membre = $resultat;
        } else {
            // Le compte n'existe plus : on nettoie la session
            unset($_SESSION['membre_id']);
        }
    }

    return $membre;
}


/** Indique si un membre est connecté. */
function est_connecte(): bool
{
    return membre_connecte() !== null;
}


/**
 * Réserve une page aux membres connectés.
 * Un visiteur est renvoyé vers la page de connexion.
 */
function exiger_connexion(): void
{
    if (!est_connecte()) {
        message_flash('info', 'Connectez-vous pour accéder à cette page.');
        rediriger('modules/members/connexion.php');
    }
}


/**
 * Réserve une page aux visiteurs (inscription, connexion).
 * Un membre déjà connecté est renvoyé vers l'accueil.
 */
function exiger_visiteur(): void
{
    if (est_connecte()) {
        rediriger('');
    }
}


/** Connecte un membre (après vérification de son mot de passe). */
function connecter_membre(int $id): void
{
    // Nouvel identifiant de session : empêche le "vol de session"
    session_regenerate_id(true);

    $_SESSION['membre_id'] = $id;

    $requete = bdd()->prepare('UPDATE membres SET derniere_connexion = NOW() WHERE id = :id');
    $requete->execute(['id' => $id]);
}


/** Déconnecte complètement le membre. */
function deconnecter_membre(): void
{
    $_SESSION = [];                 // vide toutes les données de session
    session_regenerate_id(true);    // change l'identifiant de session
}
