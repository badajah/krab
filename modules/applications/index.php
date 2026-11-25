<?php
/* ==========================================================================
   KRAB — modules/applications/index.php
   --------------------------------------------------------------------------
   Page « Invitations » : tout ce qui concerne les invitations et les
   candidatures, en quatre parties :

     1. Invitations reçues      → j'accepte ou je refuse
     2. Candidatures reçues     → des membres se proposent sur mes missions
     3. Mes candidatures        → les missions où je me suis proposé
     4. Invitations envoyées    → les membres que j'ai invités, et leur réponse

   Les parties vides ne sont pas affichées.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

/**
 * Lit des candidatures avec leur mission et la personne concernée.
 * $condition : condition SQL écrite par nous (jamais de valeur venant d'un visiteur)
 * $personne  : 'auteur' (la personne affichée est l'auteur de la mission)
 *              ou 'membre' (la personne affichée est le membre candidat / invité)
 */
function lire_liste(string $condition, string $personne, int $moi_id): array
{
    $jointure = $personne === 'auteur' ? 'p.id = mi.auteur_id' : 'p.id = ca.membre_id';
    $requete = bdd()->prepare(
        "SELECT ca.id AS candidature_id, ca.statut AS statut_candidature, ca.message,
                mi.*, p.id AS personne_id, p.prenom AS personne_prenom, p.nom AS personne_nom, p.photo AS personne_photo
         FROM candidatures ca
         JOIN missions mi ON mi.id = ca.mission_id
         JOIN membres p ON $jointure
         WHERE $condition
         ORDER BY mi.date_debut"
    );
    $requete->execute(['moi' => $moi_id]);
    return $requete->fetchAll();
}

$invitations_recues = lire_liste(
    "ca.membre_id = :moi AND ca.statut = 'invitee' AND mi.statut = 'ouverte'
     AND COALESCE(mi.date_fin, mi.date_debut) >= CURDATE()", 'auteur', $moi_id);

$candidatures_recues = lire_liste(
    "mi.auteur_id = :moi AND ca.statut = 'en_attente' AND mi.statut = 'ouverte'", 'membre', $moi_id);

$mes_candidatures = lire_liste(
    "ca.membre_id = :moi AND ca.origine = 'candidature' AND ca.statut IN ('en_attente', 'acceptee', 'refusee')", 'auteur', $moi_id);

$invitations_envoyees = lire_liste(
    "mi.auteur_id = :moi AND ca.origine = 'invitation' AND ca.statut IN ('invitee', 'acceptee', 'refusee')", 'membre', $moi_id);


/** Une ligne de la page : la personne, la mission, l'état, et des boutons. */
function ligne(array $element, string $boutons = '', bool $a_traiter = false): string
{
    $personne = ['id' => $element['personne_id'], 'prenom' => $element['personne_prenom'],
                 'nom' => $element['personne_nom'], 'photo' => $element['personne_photo']];
    ob_start(); ?>
    <li class="element<?= $a_traiter ? ' element-non-lu' : '' ?>">
        <div>
            <div class="membre-resume">
                <?= avatar($personne) ?>
                <div>
                    <p class="membre-nom">
                        <a href="<?= e(url('modules/listings/voir.php?id=' . (int) $element['id'])) ?>"><?= e($element['titre']) ?></a>
                    </p>
                    <p class="membre-info">
                        <?= e($element['personne_prenom'] . ' ' . $element['personne_nom']) ?> · <?= e(dates_mission($element)) ?>
                    </p>
                </div>
            </div>
            <?php if (!empty($element['message'])): ?>
                <p class="texte-petit"><?= nl2br(e($element['message'])) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($element['statut'] === 'annulee'): ?>
            <span class="etiquette etiquette-refusee">Mission annulée</span>
        <?php elseif ($boutons !== ''): ?>
            <div class="groupe-boutons"><?= $boutons ?></div>
        <?php else: ?>
            <?= etiquette_candidature($element['statut_candidature']) ?>
        <?php endif; ?>
    </li>
    <?php return ob_get_clean();
}

$titre_page  = 'Invitations';
$page_active = 'invitations';
require __DIR__ . '/../../includes/entete.php';
$rien = !$invitations_recues && !$candidatures_recues && !$mes_candidatures && !$invitations_envoyees;
?>

<div class="entete-page">
    <h1>Invitations</h1>
    <p class="texte-discret">Les invitations et les candidatures, reçues et envoyées.</p>
</div>

<?php if ($rien): ?>
    <div class="etat-vide">
        <p>Rien pour l'instant. Les missions proposées apparaissent dans votre agenda.</p>
        <a class="bouton bouton-secondaire" href="<?= e(url('modules/agenda/index.php')) ?>">Ouvrir mon agenda</a>
    </div>
<?php endif; ?>

<?php if ($invitations_recues): ?>
    <section class="section">
        <h2>Invitations reçues</h2>
        <ul class="liste-elements">
            <?php foreach ($invitations_recues as $element): ?>
                <?= ligne($element,
                    bouton_action('accepter_invitation', ['id' => (int) $element['candidature_id']], 'Accepter', 'bouton bouton-succes bouton-petit')
                  . bouton_action('refuser_invitation', ['id' => (int) $element['candidature_id']], 'Refuser', 'bouton bouton-secondaire bouton-petit'),
                    true) ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($candidatures_recues): ?>
    <section class="section">
        <h2>Candidatures reçues sur mes missions</h2>
        <ul class="liste-elements">
            <?php foreach ($candidatures_recues as $element):
                $boutons = (mission_complete(lire_mission((int) $element['id']))
                            ? ''
                            : bouton_action('accepter', ['id' => (int) $element['candidature_id']], 'Accepter', 'bouton bouton-succes bouton-petit'))
                         . bouton_action('refuser', ['id' => (int) $element['candidature_id']], 'Refuser', 'bouton bouton-secondaire bouton-petit');
            ?>
                <?= ligne($element, $boutons, true) ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($mes_candidatures): ?>
    <section class="section">
        <h2>Mes candidatures</h2>
        <ul class="liste-elements">
            <?php foreach ($mes_candidatures as $element): ?>
                <?= ligne($element) ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($invitations_envoyees): ?>
    <section class="section">
        <h2>Invitations envoyées</h2>
        <ul class="liste-elements">
            <?php foreach ($invitations_envoyees as $element):
                $boutons = ($element['statut_candidature'] === 'invitee' && $element['statut'] === 'ouverte')
                    ? etiquette_candidature('invitee')
                      . bouton_action('annuler_invitation', ['id' => (int) $element['candidature_id']], 'Annuler', 'bouton bouton-discret bouton-petit')
                    : '';
            ?>
                <?= ligne($element, $boutons) ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php
require __DIR__ . '/../../includes/pied-page.php';
