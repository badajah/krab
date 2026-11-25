<?php
/* ==========================================================================
   KRAB — modules/agenda/fonctions.php
   --------------------------------------------------------------------------
   Fonctions communes de l'agenda : disponibilités, visibilité,
   recherche des membres libres et outils pour l'affichage par mois.

   RÈGLE (CLAUDE.md, section 7) : la visibilité des disponibilités est
   contrôlée ICI, côté serveur, dans les requêtes SQL.
   ========================================================================== */


/** Les visibilités possibles d'un créneau : code => texte affiché. */
function visibilites_dispo(): array
{
    return [
        'membres' => 'Tous les membres',
        'ciblee'  => 'Membres choisis',
        'privee'  => 'Privé (visible par moi seul)',
    ];
}


/** Étiquette HTML de la visibilité d'un créneau. */
function etiquette_visibilite_dispo(string $visibilite): string
{
    $etiquettes = [
        'membres' => ['etiquette-membres', 'Tous les membres'],
        'ciblee'  => ['etiquette-ciblee',  'Membres choisis'],
        'privee'  => ['etiquette-privee',  'Privé'],
    ];
    [$classe, $texte] = $etiquettes[$visibilite];
    return '<span class="etiquette ' . $classe . '">' . e($texte) . '</span>';
}


/** « 09:00:00 » → « 09:00 » */
function heure_courte(string $heure): string
{
    return substr($heure, 0, 5);
}


/** « 09:00 – 12:30 » */
function horaire(array $dispo): string
{
    return heure_courte($dispo['heure_debut']) . ' – ' . heure_courte($dispo['heure_fin']);
}


/**
 * Condition SQL : « ce créneau est visible par le membre :lecteur ».
 * Le texte est écrit par nous ; l'identifiant du lecteur passe
 * uniquement par le marqueur :lecteur (requête préparée).
 * $alias = nom donné à la table disponibilites dans la requête (ex. 'd').
 */
function condition_dispo_visible(string $alias): string
{
    return "($alias.visibilite = 'membres'
             OR ($alias.visibilite = 'ciblee'
                 AND EXISTS (SELECT 1 FROM disponibilites_cibles dc
                             WHERE dc.disponibilite_id = $alias.id AND dc.membre_id = :lecteur)))";
}


/** Mes propres créneaux entre deux dates (incluses). */
function mes_disponibilites(int $membre_id, string $du, string $au): array
{
    $requete = bdd()->prepare(
        'SELECT * FROM disponibilites
         WHERE membre_id = :membre AND date_jour BETWEEN :du AND :au
         ORDER BY date_jour, heure_debut'
    );
    $requete->execute(['membre' => $membre_id, 'du' => $du, 'au' => $au]);
    return $requete->fetchAll();
}


/** Créneaux d'un AUTRE membre que je peux voir, entre deux dates. */
function disponibilites_visibles(int $membre_id, int $lecteur_id, string $du, string $au): array
{
    $requete = bdd()->prepare(
        'SELECT d.* FROM disponibilites d
         WHERE d.membre_id = :membre AND d.date_jour BETWEEN :du AND :au
           AND ' . condition_dispo_visible('d') . '
         ORDER BY d.date_jour, d.heure_debut'
    );
    $requete->execute(['membre' => $membre_id, 'du' => $du, 'au' => $au, 'lecteur' => $lecteur_id]);
    return $requete->fetchAll();
}


/**
 * Membres LIBRES pour une mission. Un membre est proposé seulement si :
 *   1. il a la compétence demandée par la mission (si elle en demande une) ;
 *   2. il est disponible AU MOMENT de la mission, CHAQUE jour de la mission :
 *      il a un créneau visible par moi qui couvre les heures de la mission
 *      (si la mission n'a pas d'horaire, un créneau ce jour-là suffit) ;
 *   3. il n'est pas déjà invité, candidat, confirmé ou refusé sur cette mission.
 */
function membres_libres(array $mission, int $lecteur_id): array
{
    $du = $mission['date_debut'];
    $au = $mission['date_fin'] ?: $mission['date_debut'];

    // Nombre de jours de la mission (ex. du 3 au 5 → 3 jours)
    $nombre_jours = (int) (new DateTimeImmutable($du))->diff(new DateTimeImmutable($au))->days + 1;

    $conditions = [
        'd.date_jour BETWEEN :du AND :au',
        'm.id <> :moi',
        condition_dispo_visible('d'),
        "m.id NOT IN (SELECT ca.membre_id FROM candidatures ca
                      WHERE ca.mission_id = :mission AND ca.statut <> 'retiree')",
    ];
    $parametres = ['du' => $du, 'au' => $au, 'moi' => $lecteur_id,
                   'lecteur' => $lecteur_id, 'mission' => $mission['id']];

    // 1. Compétence demandée
    if (!empty($mission['competence_id'])) {
        $conditions[] = 'EXISTS (SELECT 1 FROM membres_competences mc
                                 WHERE mc.membre_id = m.id AND mc.competence_id = :competence)';
        $parametres['competence'] = $mission['competence_id'];
    }

    // 2. Le créneau doit couvrir les heures de la mission
    if (!empty($mission['heure_debut']) && !empty($mission['heure_fin'])) {
        $conditions[] = 'd.heure_debut <= :heure_debut AND d.heure_fin >= :heure_fin';
        $parametres['heure_debut'] = $mission['heure_debut'];
        $parametres['heure_fin']   = $mission['heure_fin'];
    }

    // HAVING : le membre doit être disponible TOUS les jours de la mission.
    // $nombre_jours est un nombre entier calculé par nous (pas une donnée
    // envoyée par un visiteur) : on peut l'écrire directement dans la requête.
    $requete = bdd()->prepare(
        'SELECT m.id, m.prenom, m.nom
         FROM disponibilites d
         JOIN membres m ON m.id = d.membre_id
         WHERE ' . implode(' AND ', $conditions) . '
         GROUP BY m.id, m.prenom, m.nom
         HAVING COUNT(DISTINCT d.date_jour) = ' . $nombre_jours . '
         ORDER BY m.nom, m.prenom'
    );
    $requete->execute($parametres);
    return $requete->fetchAll();
}


/* --------------------------------------------------------------------------
   OUTILS POUR L'AFFICHAGE PAR MOIS
   -------------------------------------------------------------------------- */

/** Noms des mois en français. */
function nom_mois(int $numero): string
{
    $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
             'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $mois[$numero];
}


/**
 * Lit le mois demandé dans l'adresse (?mois=2026-12).
 * Renvoie le premier jour du mois (objet DateTimeImmutable).
 * Mois absent ou invalide → mois actuel.
 */
function mois_demande(): DateTimeImmutable
{
    $texte = (string) ($_GET['mois'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $texte)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $texte . '-01');
        if ($date !== false) {
            return $date;
        }
    }
    return new DateTimeImmutable('first day of this month midnight');
}


/**
 * Les jours à afficher dans la grille d'un mois : des semaines complètes,
 * du lundi au dimanche (avec quelques jours des mois voisins).
 * Renvoie une liste de semaines, chaque semaine étant une liste de 7 dates 'AAAA-MM-JJ'.
 */
function semaines_du_mois(DateTimeImmutable $premier_jour): array
{
    // Recule jusqu'au lundi (format 'N' : 1 = lundi ... 7 = dimanche)
    $jour = $premier_jour->modify('-' . ((int) $premier_jour->format('N') - 1) . ' days');
    $dernier = $premier_jour->modify('last day of this month');

    $semaines = [];
    while ($jour <= $dernier) {
        $semaine = [];
        for ($i = 0; $i < 7; $i++) {
            $semaine[] = $jour->format('Y-m-d');
            $jour = $jour->modify('+1 day');
        }
        $semaines[] = $semaine;
    }
    return $semaines;
}


/** Ajoute un élément à afficher dans l'agenda, pour chaque jour d'une période. */
function ajouter_evenement(array &$evenements, string $du, string $au, array $evenement): void
{
    $jour = new DateTimeImmutable($du);
    $fin  = new DateTimeImmutable($au);
    while ($jour <= $fin) {
        $evenements[$jour->format('Y-m-d')][] = $evenement;
        $jour = $jour->modify('+1 day');
    }
}
