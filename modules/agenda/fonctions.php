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
 *   - visibilité « Tous les membres » ;
 *   - ou « Membres choisis » et le lecteur est choisi (directement ou
 *     par un de ses groupes, voir includes/groupes.php).
 * $alias = nom donné à la table disponibilites dans la requête (ex. 'd').
 */
function condition_dispo_visible(string $alias): string
{
    return "($alias.visibilite = 'membres'
             OR ($alias.visibilite = 'ciblee' AND " . condition_est_cible('dispo', "$alias.id") . "))";
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
 * Membres qu'on peut inviter sur une mission, en deux listes :
 *
 *   'disponibles' → ont la compétence demandée (si la mission en demande une)
 *                   ET sont libres au moment de la mission, CHAQUE jour,
 *                   selon un créneau qu'ils m'ont rendu visible ;
 *   'autres'      → tous les autres membres (on peut quand même les inviter).
 *
 * Les membres déjà invités, candidats, confirmés ou refusés sur cette
 * mission ne sont dans aucune des deux listes.
 */
function membres_pour_invitation(array $mission, int $moi_id): array
{
    $du = $mission['date_debut'];
    $au = $mission['date_fin'] ?: $mission['date_debut'];

    // 1. Membres déjà concernés par la mission : exclus
    $requete = bdd()->prepare("SELECT membre_id FROM candidatures WHERE mission_id = :mission AND statut <> 'retiree'");
    $requete->execute(['mission' => $mission['id']]);
    $exclus = array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));

    $tous = array_filter(autres_membres($moi_id), fn($m) => !in_array((int) $m['id'], $exclus, true));

    // 2. Créneaux visibles par moi pendant la mission, des membres qui ont la compétence
    $conditions = ['d.date_jour BETWEEN :du AND :au', 'd.membre_id <> :moi', condition_dispo_visible('d')];
    $parametres = ['du' => $du, 'au' => $au, 'moi' => $moi_id, 'lecteur' => $moi_id];
    if (!empty($mission['competence_id'])) {
        $conditions[] = 'EXISTS (SELECT 1 FROM membres_competences mc
                                 WHERE mc.membre_id = d.membre_id AND mc.competence_id = :competence)';
        $parametres['competence'] = $mission['competence_id'];
    }
    $requete = bdd()->prepare(
        'SELECT d.membre_id, d.date_jour, d.heure_debut, d.heure_fin
         FROM disponibilites d
         WHERE ' . implode(' AND ', $conditions)
    );
    $requete->execute($parametres);

    // Range les créneaux par membre puis par jour
    $creneaux = [];
    foreach ($requete->fetchAll() as $ligne) {
        $creneaux[(int) $ligne['membre_id']][$ligne['date_jour']][] = $ligne;
    }

    // 3. Sépare les membres en « disponibles » et « autres »
    $disponibles = [];
    $autres = [];
    foreach ($tous as $membre) {
        if (est_libre_pour_mission($creneaux[(int) $membre['id']] ?? [], $mission)) {
            $disponibles[] = $membre;
        } else {
            $autres[] = $membre;
        }
    }
    return ['disponibles' => $disponibles, 'autres' => $autres];
}


/**
 * Ces créneaux (rangés par jour) couvrent-ils toute la mission ?
 *
 * Sans horaire : il faut au moins un créneau CHAQUE jour de la mission.
 * Avec horaire (ex. mardi 14:00 → jeudi 10:00) :
 *   - mission d'un seul jour : un créneau qui couvre de l'heure de début à l'heure de fin ;
 *   - premier jour : un créneau qui commence au plus tard à l'heure de début ;
 *   - jours du milieu : au moins un créneau ;
 *   - dernier jour : un créneau qui finit au plus tôt à l'heure de fin.
 */
function est_libre_pour_mission(array $creneaux_par_jour, array $mission): bool
{
    $premier = $mission['date_debut'];
    $dernier = $mission['date_fin'] ?: $mission['date_debut'];
    $avec_horaire = !empty($mission['heure_debut']) && !empty($mission['heure_fin']);
    $heure_debut  = $avec_horaire ? heure_courte($mission['heure_debut']) : '';
    $heure_fin    = $avec_horaire ? heure_courte($mission['heure_fin'])   : '';

    $jour = new DateTimeImmutable($premier);
    $fin  = new DateTimeImmutable($dernier);

    while ($jour <= $fin) {
        $date = $jour->format('Y-m-d');
        $ok = false;

        foreach ($creneaux_par_jour[$date] ?? [] as $creneau) {
            $debut_creneau = heure_courte($creneau['heure_debut']);
            $fin_creneau   = heure_courte($creneau['heure_fin']);

            if (!$avec_horaire) {
                $ok = true;
            } elseif ($premier === $dernier) {
                $ok = $debut_creneau <= $heure_debut && $fin_creneau >= $heure_fin;
            } elseif ($date === $premier) {
                $ok = $debut_creneau <= $heure_debut;
            } elseif ($date === $dernier) {
                $ok = $fin_creneau >= $heure_fin;
            } else {
                $ok = true;
            }
            if ($ok) {
                break;
            }
        }

        if (!$ok) {
            return false;   // un seul jour sans créneau qui convient suffit à écarter le membre
        }
        $jour = $jour->modify('+1 day');
    }
    return true;
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
