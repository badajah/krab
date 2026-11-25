<?php
/* ==========================================================================
   KRAB — modules/listings/fonctions.php
   --------------------------------------------------------------------------
   Fonctions communes aux missions et aux candidatures.

   RÈGLE IMPORTANTE (CLAUDE.md, section 7) : les contrôles de visibilité
   sont faits ICI, côté serveur. Chaque page qui affiche ou modifie une
   mission passe par ces fonctions.
   ========================================================================== */

const PLACES_MAXIMUM = 20;   // nombre de places maximum pour une mission


/** Les visibilités possibles : code => texte affiché. */
function visibilites(): array
{
    return [
        'publique' => 'Tous les membres',
        'ciblee'   => 'Membres choisis',
        'privee'   => 'Privée (visible par moi seul)',
    ];
}


/** Étiquette HTML de la visibilité d'une mission. */
function etiquette_visibilite(string $visibilite): string
{
    $classes = ['publique' => 'etiquette-membres', 'ciblee' => 'etiquette-ciblee', 'privee' => 'etiquette-privee'];
    $textes  = ['publique' => 'Tous les membres',  'ciblee' => 'Membres choisis',  'privee' => 'Privée'];
    return '<span class="etiquette ' . $classes[$visibilite] . '">' . e($textes[$visibilite]) . '</span>';
}


/** Étiquette HTML de l'état d'une candidature. */
function etiquette_candidature(string $statut): string
{
    $etats = [
        'en_attente' => ['etiquette-attente',   'En attente'],
        'acceptee'   => ['etiquette-confirmee', 'Confirmée'],
        'refusee'    => ['etiquette-refusee',   'Refusée'],
        'retiree'    => ['etiquette-refusee',   'Retirée'],
    ];
    [$classe, $texte] = $etats[$statut];
    return '<span class="etiquette ' . $classe . '">' . e($texte) . '</span>';
}


/** Dates d'une mission en texte : « 03.01.2027 » ou « du 03.01.2027 au 05.01.2027 ». */
function dates_mission(array $mission): string
{
    $debut = date('d.m.Y', strtotime($mission['date_debut']));
    if (empty($mission['date_fin']) || $mission['date_fin'] === $mission['date_debut']) {
        return $debut;
    }
    return 'du ' . $debut . ' au ' . date('d.m.Y', strtotime($mission['date_fin']));
}


/** Lieu d'une mission en texte : « Sion · Valais ». */
function lieu_mission(array $mission): string
{
    return implode(' · ', array_filter([$mission['localite'] ?? '', nom_canton($mission['canton'] ?? null)]));
}


/**
 * Lit une mission avec le nom de son auteur et de sa compétence.
 * Renvoie null si elle n'existe pas.
 */
function lire_mission(int $id): ?array
{
    $requete = bdd()->prepare(
        'SELECT mi.*, me.prenom AS auteur_prenom, me.nom AS auteur_nom, c.nom AS competence_nom,
                (SELECT COUNT(*) FROM candidatures ca
                 WHERE ca.mission_id = mi.id AND ca.statut = \'acceptee\') AS places_prises
         FROM missions mi
         JOIN membres me ON me.id = mi.auteur_id
         LEFT JOIN competences c ON c.id = mi.competence_id
         WHERE mi.id = :id'
    );
    $requete->execute(['id' => $id]);
    return $requete->fetch() ?: null;
}


/** Candidature d'un membre pour une mission (ou null). */
function candidature_de(int $mission_id, int $membre_id): ?array
{
    $requete = bdd()->prepare('SELECT * FROM candidatures WHERE mission_id = :mission AND membre_id = :membre');
    $requete->execute(['mission' => $mission_id, 'membre' => $membre_id]);
    return $requete->fetch() ?: null;
}


/** Le membre fait-il partie des membres choisis pour cette mission ? */
function est_cible(int $mission_id, int $membre_id): bool
{
    $requete = bdd()->prepare('SELECT 1 FROM missions_cibles WHERE mission_id = :mission AND membre_id = :membre');
    $requete->execute(['mission' => $mission_id, 'membre' => $membre_id]);
    return (bool) $requete->fetchColumn();
}


/**
 * CONTRÔLE D'ACCÈS : ce membre peut-il voir cette mission ?
 *   - l'auteur voit toujours sa mission ;
 *   - un membre qui s'est déjà proposé garde l'accès (pour suivre sa candidature) ;
 *   - sinon, cela dépend de la visibilité.
 */
function peut_voir_mission(array $mission, int $membre_id): bool
{
    if ((int) $mission['auteur_id'] === $membre_id) {
        return true;
    }
    if (candidature_de((int) $mission['id'], $membre_id) !== null) {
        return true;
    }
    return match ($mission['visibilite']) {
        'publique' => true,
        'ciblee'   => est_cible((int) $mission['id'], $membre_id),
        default    => false,   // 'privee'
    };
}


/** La mission est-elle complète (toutes les places prises) ? */
function mission_complete(array $mission): bool
{
    return (int) $mission['places_prises'] >= (int) $mission['nombre_places'];
}


/**
 * Ce membre peut-il se proposer pour cette mission ?
 * Renvoie '' si oui, sinon la raison (texte à afficher).
 */
function raison_refus_candidature(array $mission, int $membre_id): string
{
    if ((int) $mission['auteur_id'] === $membre_id) {
        return 'C\'est votre mission.';
    }
    if ($mission['statut'] === 'annulee') {
        return 'Cette mission a été annulée.';
    }
    if ($mission['visibilite'] === 'privee') {
        return 'Cette mission n\'est pas encore publiée.';
    }
    if (($mission['date_fin'] ?: $mission['date_debut']) < date('Y-m-d')) {
        return 'Cette mission est terminée.';
    }
    if (mission_complete($mission)) {
        return 'Toutes les places sont prises.';
    }
    return '';
}
