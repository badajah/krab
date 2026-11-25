<?php
/* ==========================================================================
   KRAB — modules/agenda/index.php
   --------------------------------------------------------------------------
   Mon agenda, mois par mois. On y trouve automatiquement :
     - mes disponibilités (vert) ;
     - les missions où ma participation est confirmée (rouge brique) ;
     - les missions en attente : candidature envoyée ou invitation reçue (orange) ;
     - les missions que j'ai publiées (gris-bleu). En cliquant dessus, on
       arrive sur la mission, avec la liste des membres libres à inviter.

   Adresse : index.php?mois=2026-12  (sans « mois » : le mois actuel)
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

$mois     = mois_demande();
$semaines = semaines_du_mois($mois);
$du = $semaines[0][0];                       // premier jour affiché
$au = end($semaines)[6];                     // dernier jour affiché

$evenements = [];

// ---- 1. Mes disponibilités ------------------------------------------------
foreach (mes_disponibilites($moi_id, $du, $au) as $dispo) {
    ajouter_evenement($evenements, $dispo['date_jour'], $dispo['date_jour'], [
        'type'  => 'dispo',
        'texte' => 'Libre ' . horaire($dispo),
        'lien'  => url('modules/agenda/disponibilite.php?id=' . (int) $dispo['id']),
    ]);
}

// Condition commune : la mission touche la période affichée
$periode = 'mi.date_debut <= :au AND COALESCE(mi.date_fin, mi.date_debut) >= :du';

// ---- 2. Les missions que j'ai publiées ------------------------------------
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
        'texte' => $mission['titre'] . ' (' . $mission['places_prises'] . '/' . $mission['nombre_places'] . ')',
        // #inviter : on arrive directement sur la liste des membres libres
        'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']) . '#inviter',
    ]);
}

// ---- 3. Les missions auxquelles je participe ou suis invité ---------------
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
    $confirmee = $mission['statut_candidature'] === 'acceptee';
    $prefixe = match ($mission['statut_candidature']) {
        'acceptee' => '',
        'invitee'  => 'Invitation : ',
        default    => 'En attente : ',
    };
    $horaire = !empty($mission['heure_debut']) ? heure_courte($mission['heure_debut']) . ' ' : '';
    ajouter_evenement($evenements, $mission['date_debut'], $mission['date_fin'] ?: $mission['date_debut'], [
        'type'  => $confirmee ? 'confirmee' : 'attente',
        'texte' => $prefixe . $horaire . $mission['titre'],
        'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']),
    ]);
}

// ---- Affichage ------------------------------------------------------------
$adresse_mois = fn(string $m) => url('modules/agenda/index.php?mois=' . $m);
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

<ul class="agenda-legende">
    <li><span class="agenda-evenement agenda-evenement-dispo"></span>Mes disponibilités</li>
    <li><span class="agenda-evenement agenda-evenement-confirmee"></span>Missions confirmées</li>
    <li><span class="agenda-evenement agenda-evenement-attente"></span>En attente / invitations</li>
    <li><span class="agenda-evenement agenda-evenement-ma-mission"></span>Mes missions publiées</li>
</ul>

<?php require __DIR__ . '/_grille.php'; ?>

<p class="texte-discret texte-petit">
    Astuce : cliquez sur le numéro d'un jour pour y ajouter une disponibilité.
</p>

<?php
require __DIR__ . '/../../includes/pied-page.php';
