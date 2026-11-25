<?php
/* ==========================================================================
   KRAB — modules/agenda/membre.php
   --------------------------------------------------------------------------
   Disponibilités d'un AUTRE membre, mois par mois, et invitation de ce
   membre sur une de mes missions.

   - On n'affiche que les créneaux qu'il a rendus visibles pour moi
     (contrôle dans la requête SQL, côté serveur). Ses missions et ses
     créneaux privés ne sont jamais montrés.
   - MES missions ouvertes apparaissent « en filigrane » : on voit si elles
     tombent sur ses disponibilités, et un clic l'invite sur la mission.

   Adresse : membre.php?id=12&mois=2026-12
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

$membre_id = (int) ($_GET['id'] ?? 0);

// Mon propre agenda : direction ma page
if ($membre_id === $moi_id) {
    rediriger('modules/agenda/index.php');
}

$requete = bdd()->prepare('SELECT id, prenom, nom, photo FROM membres WHERE id = :id');
$requete->execute(['id' => $membre_id]);
$membre = $requete->fetch();
if (!$membre) {
    http_response_code(404);
    exit('Membre introuvable.');
}

$mois     = mois_demande();
$semaines = semaines_du_mois($mois);

// ---- Ses créneaux visibles pour moi ---------------------------------------
$evenements = [];
foreach (disponibilites_visibles($membre_id, $moi_id, $semaines[0][0], end($semaines)[6]) as $dispo) {
    ajouter_evenement($evenements, $dispo['date_jour'], $dispo['date_jour'], [
        'type'  => 'dispo',
        'texte' => 'Libre ' . horaire($dispo),
        'lien'  => '',
    ]);
}

// ---- Mes missions ouvertes, en filigrane ----------------------------------
// Pour chacune : la situation de ce membre sur la mission (pas encore invité,
// invité, candidat, confirmé, refusé).
$requete = bdd()->prepare(
    "SELECT mi.*,
            (SELECT COUNT(*) FROM candidatures ca WHERE ca.mission_id = mi.id AND ca.statut = 'acceptee') AS places_prises,
            (SELECT ca.statut FROM candidatures ca WHERE ca.mission_id = mi.id AND ca.membre_id = :membre) AS sa_situation
     FROM missions mi
     WHERE mi.auteur_id = :moi
       AND mi.statut = 'ouverte'
       AND COALESCE(mi.date_fin, mi.date_debut) >= CURDATE()
     ORDER BY mi.date_debut"
);
$requete->execute(['moi' => $moi_id, 'membre' => $membre_id]);
$mes_missions = $requete->fetchAll();

$situations = [
    'invitee'    => 'invité·e',
    'en_attente' => 'candidat·e',
    'acceptee'   => 'confirmé·e',
    'refusee'    => 'refusé·e',
];
$invitables = [];   // missions sur lesquelles on peut encore l'inviter

foreach ($mes_missions as $mission) {
    $situation = $mission['sa_situation'];
    $peut_inviter = ($situation === null || $situation === 'retiree') && !mission_complete($mission);
    $horaire = !empty($mission['heure_debut']) ? heure_courte($mission['heure_debut']) . ' ' : '';

    if ($peut_inviter) {
        $invitables[] = $mission;
        $evenement = [
            'type'         => 'filigrane',
            'texte'        => '+ ' . $horaire . $mission['titre'],
            'action'       => ['action' => 'inviter', 'id' => (int) $mission['id'], 'membre_id' => $membre_id],
            'confirmation' => 'Inviter ' . $membre['prenom'] . ' sur « ' . $mission['titre'] . ' » ?',
        ];
    } else {
        $etat = isset($situations[$situation]) ? $membre['prenom'] . ' ' . $situations[$situation] : 'complète';
        $evenement = [
            'type'  => 'filigrane',
            'texte' => $horaire . $mission['titre'] . ' (' . $etat . ')',
            'lien'  => url('modules/listings/voir.php?id=' . (int) $mission['id']),
        ];
    }
    ajouter_evenement($evenements, $mission['date_debut'], $mission['date_fin'] ?: $mission['date_debut'], $evenement);
}

$adresse_mois = fn(string $m) => url('modules/agenda/membre.php?id=' . $membre_id . '&mois=' . $m);
$lien_ajout   = false;

$titre_page          = 'Disponibilités de ' . $membre['prenom'] . ' ' . $membre['nom'];
$page_active         = 'membres';
$vue                 = 'mois';
$css_supplementaires = ['agenda.css'];
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <div class="membre-resume">
        <?= avatar($membre) ?>
        <div>
            <h1>Disponibilités de <?= e($membre['prenom']) ?></h1>
            <p class="membre-info">
                <a href="<?= e(url('modules/members/profil.php?id=' . $membre_id)) ?>">Voir son profil</a>
            </p>
        </div>
    </div>
</div>

<section class="section">
    <h2>Inviter <?= e($membre['prenom']) ?> sur une de mes missions</h2>
    <?php if (!$invitables): ?>
        <p class="texte-discret">
            Vous n'avez pas de mission ouverte sur laquelle l'inviter.
            <a href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
        </p>
    <?php else: ?>
        <form class="formulaire" method="post" action="<?= e(url('modules/applications/action.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="inviter">
            <input type="hidden" name="membre_id" value="<?= $membre_id ?>">
            <div class="champ">
                <label for="mission">Mission</label>
                <select id="mission" name="id" required>
                    <?php foreach ($invitables as $mission): ?>
                        <option value="<?= (int) $mission['id'] ?>"><?= e($mission['titre'] . ' — ' . dates_mission($mission)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="bouton" type="submit">Inviter</button>
        </form>
    <?php endif; ?>
</section>

<section class="section">
    <?php require __DIR__ . '/_grille.php'; ?>
    <p class="texte-discret texte-petit">
        En vert : les créneaux que <?= e($membre['prenom']) ?> a choisi de vous montrer.
        En pointillés : vos missions. Cliquez sur une mission marquée « + » pour inviter <?= e($membre['prenom']) ?>.
    </p>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
