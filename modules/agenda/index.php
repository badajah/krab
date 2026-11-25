<?php
/* ==========================================================================
   KRAB — modules/agenda/index.php
   --------------------------------------------------------------------------
   MON AGENDA : la page centrale de KRAB.

   Deux vues :      ?vue=mois  (grille, par défaut)   ou   ?vue=liste
   Trois filtres (cases à cocher) pour afficher ou masquer :
     - dispos    → mes disponibilités (vert) ;
     - missions  → mes missions : celles que j'ai publiées (gris-bleu),
                   celles où je suis confirmé (rouge brique),
                   en attente ou invitation (orange) ;
     - proposees → les missions des autres, visibles pour moi, auxquelles
                   je peux me proposer (bleu, pointillés).

   Exemple d'adresse : index.php?mois=2026-12&vue=liste&filtre=1&afficher[]=dispos
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

// ---- Vue et filtres choisis ----------------------------------------------
$vue = ($_GET['vue'] ?? '') === 'liste' ? 'liste' : 'mois';

$tous_les_filtres = [
    'dispos'    => 'Mes disponibilités',
    'missions'  => 'Mes missions',
    'proposees' => 'Missions proposées',
];
// « filtre=1 » indique que le formulaire a été envoyé ; sinon, tout est affiché
if (isset($_GET['filtre'])) {
    $demandes = is_array($_GET['afficher'] ?? null) ? $_GET['afficher'] : [];
    $afficher = array_values(array_intersect(array_keys($tous_les_filtres), $demandes));
} else {
    $afficher = array_keys($tous_les_filtres);
}

$mois     = mois_demande();
$semaines = semaines_du_mois($mois);
$du = $semaines[0][0];                       // premier jour affiché
$au = end($semaines)[6];                     // dernier jour affiché

$evenements = [];

// Condition commune : la mission touche la période affichée
$periode = 'mi.date_debut <= :au AND COALESCE(mi.date_fin, mi.date_debut) >= :du';

/** Texte court d'une mission dans l'agenda : « 14:00 Titre » */
$texte_mission = fn(array $m, string $prefixe = '') =>
    $prefixe . (!empty($m['heure_debut']) ? heure_courte($m['heure_debut']) . ' ' : '') . $m['titre'];


// ---- 1. Mes disponibilités ------------------------------------------------
if (in_array('dispos', $afficher, true)) {
    foreach (mes_disponibilites($moi_id, $du, $au) as $dispo) {
        ajouter_evenement($evenements, $dispo['date_jour'], $dispo['date_jour'], [
            'type'  => 'dispo',
            'texte' => 'Libre ' . horaire($dispo),
            'lien'  => url('modules/agenda/disponibilite.php?id=' . (int) $dispo['id']),
        ]);
    }
}

// ---- 2. Mes missions ------------------------------------------------------
if (in_array('missions', $afficher, true)) {

    // a) Celles que j'ai publiées
    $requete = bdd()->prepare(
        "SELECT mi.*,
                (SELECT COUNT(*) FROM candidatures ca WHERE ca.mission_id = mi.id AND ca.statut = 'acceptee') AS places_prises
         FROM missions mi
         WHERE mi.auteur_id = :moi AND mi.statut = 'ouverte' AND $periode"
    );
    $requete->execute(['moi' => $moi_id, 'du' => $du, 'au' => $au]);
    foreach ($requete->fetchAll() as $mission) {
        ajouter_evenement($evenements, $mission['date_debut'], $mission['date_fin'] ?: $mission['date_debut'], [
            'type'  => 'ma-mission',
            'texte' => $texte_mission($mission) . ' (' . $mission['places_prises'] . '/' . $mission['nombre_places'] . ')',
            // #inviter : on arrive directement sur la liste déroulante d'invitation
            'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']) . '#inviter',
        ]);
    }

    // b) Celles auxquelles je participe, où je me suis proposé ou où l'on m'invite
    $requete = bdd()->prepare(
        "SELECT mi.*, ca.statut AS statut_candidature
         FROM candidatures ca
         JOIN missions mi ON mi.id = ca.mission_id
         WHERE ca.membre_id = :moi
           AND ca.statut IN ('acceptee', 'en_attente', 'invitee')
           AND mi.statut = 'ouverte' AND $periode"
    );
    $requete->execute(['moi' => $moi_id, 'du' => $du, 'au' => $au]);
    foreach ($requete->fetchAll() as $mission) {
        $prefixe = match ($mission['statut_candidature']) {
            'acceptee' => '',
            'invitee'  => 'Invitation : ',
            default    => 'En attente : ',
        };
        ajouter_evenement($evenements, $mission['date_debut'], $mission['date_fin'] ?: $mission['date_debut'], [
            'type'  => $mission['statut_candidature'] === 'acceptee' ? 'confirmee' : 'attente',
            'texte' => $texte_mission($mission, $prefixe),
            'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']),
        ]);
    }
}

// ---- 3. Missions proposées par les autres ---------------------------------
// Visibles pour moi (contrôle côté serveur), ouvertes, pas complètes,
// et où je ne suis pas déjà impliqué (sinon elles sont dans « Mes missions »).
if (in_array('proposees', $afficher, true)) {
    $requete = bdd()->prepare(
        "SELECT mi.*
         FROM missions mi
         WHERE mi.auteur_id <> :moi
           AND mi.statut = 'ouverte'
           AND $periode
           AND (mi.visibilite = 'publique'
                OR (mi.visibilite = 'ciblee' AND " . condition_est_cible('mission', 'mi.id') . "))
           AND mi.nombre_places > (SELECT COUNT(*) FROM candidatures ca
                                   WHERE ca.mission_id = mi.id AND ca.statut = 'acceptee')
           AND NOT EXISTS (SELECT 1 FROM candidatures ca
                           WHERE ca.mission_id = mi.id AND ca.membre_id = :moi_candidat
                             AND ca.statut <> 'retiree')"
    );
    $requete->execute(['moi' => $moi_id, 'lecteur' => $moi_id, 'moi_candidat' => $moi_id, 'du' => $du, 'au' => $au]);
    foreach ($requete->fetchAll() as $mission) {
        ajouter_evenement($evenements, $mission['date_debut'], $mission['date_fin'] ?: $mission['date_debut'], [
            'type'  => 'proposee',
            'texte' => $texte_mission($mission),
            'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']),
        ]);
    }
}


// ---- Affichage ------------------------------------------------------------

/** Adresse de l'agenda en gardant la vue et les filtres. */
$adresse = function (string $mois_choisi, string $vue_choisie) use ($afficher): string {
    return url('modules/agenda/index.php?' . http_build_query([
        'mois' => $mois_choisi, 'vue' => $vue_choisie, 'filtre' => 1, 'afficher' => $afficher,
    ]));
};
$adresse_mois = fn(string $m) => $adresse($m, $vue);
$lien_ajout   = true;

$titre_page          = 'Mon agenda';
$page_active         = 'agenda';
$css_supplementaires = ['agenda.css'];
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Mon agenda</h1>
</div>

<div class="groupe-boutons">
    <a class="bouton" href="<?= e(url('modules/agenda/disponibilite.php')) ?>">Ajouter une disponibilité</a>
    <a class="bouton bouton-secondaire" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
</div>

<!-- Choix de la vue : mois ou liste -->
<nav class="onglets" aria-label="Vue de l'agenda">
    <a class="onglet<?= $vue === 'mois' ? ' actif' : '' ?>" href="<?= e($adresse($mois->format('Y-m'), 'mois')) ?>"<?= $vue === 'mois' ? ' aria-current="page"' : '' ?>>Mois</a>
    <a class="onglet<?= $vue === 'liste' ? ' actif' : '' ?>" href="<?= e($adresse($mois->format('Y-m'), 'liste')) ?>"<?= $vue === 'liste' ? ' aria-current="page"' : '' ?>>Liste</a>
</nav>

<!-- Filtres : ce que l'on veut voir. agenda.js envoie le formulaire dès qu'une case change. -->
<form class="agenda-filtres" method="get" action="" id="agenda-filtres">
    <input type="hidden" name="mois" value="<?= e($mois->format('Y-m')) ?>">
    <input type="hidden" name="vue" value="<?= e($vue) ?>">
    <input type="hidden" name="filtre" value="1">
    <?php foreach ($tous_les_filtres as $code => $texte): ?>
        <label class="choix">
            <input type="checkbox" name="afficher[]" value="<?= e($code) ?>" <?= in_array($code, $afficher, true) ? 'checked' : '' ?>>
            <span class="agenda-pastille agenda-pastille-<?= e($code) ?>" aria-hidden="true"></span>
            <?= e($texte) ?>
        </label>
    <?php endforeach; ?>
    <button class="bouton bouton-secondaire bouton-petit agenda-filtres-bouton" type="submit">Afficher</button>
</form>

<?php require __DIR__ . '/_grille.php'; ?>

<?php if ($vue === 'mois'): ?>
    <p class="texte-discret texte-petit">Astuce : cliquez sur le numéro d'un jour pour y ajouter une disponibilité.</p>
<?php endif; ?>

<script src="<?= e(url('assets/js/agenda.js')) ?>" defer></script>

<?php
require __DIR__ . '/../../includes/pied-page.php';
