<?php
/* ==========================================================================
   KRAB — modules/applications/index.php
   --------------------------------------------------------------------------
   Mes candidatures et invitations : les missions pour lesquelles je me
   suis proposé ou où l'on m'a invité, avec leur état. Les invitations
   en attente de réponse sont affichées en premier.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';

exiger_connexion();
$moi = membre_connecte();

$requete = bdd()->prepare(
    "SELECT ca.id AS candidature_id, ca.statut AS statut_candidature, mi.*, me.prenom AS auteur_prenom, me.nom AS auteur_nom
     FROM candidatures ca
     JOIN missions mi ON mi.id = ca.mission_id
     JOIN membres me ON me.id = mi.auteur_id
     WHERE ca.membre_id = :moi AND ca.statut <> 'retiree'
     ORDER BY (ca.statut = 'invitee') DESC, mi.date_debut DESC"
);
$requete->execute(['moi' => $moi['id']]);
$candidatures = $requete->fetchAll();

$titre_page   = 'Candidatures et invitations';
$page_active  = 'missions';
$onglet_actif = 'mes-candidatures';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Missions</h1>
</div>

<div class="groupe-boutons">
    <a class="bouton" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
</div>

<?php require __DIR__ . '/../listings/_onglets.php'; ?>

<section class="section">
    <?php if (!$candidatures): ?>
        <div class="etat-vide">
            <p>Aucune candidature ni invitation pour l'instant.</p>
            <a class="bouton bouton-secondaire" href="<?= e(url('modules/listings/index.php')) ?>">Voir les missions</a>
        </div>
    <?php else: ?>
        <ul class="liste-elements">
            <?php foreach ($candidatures as $mission): ?>
                <li class="element <?= $mission['statut_candidature'] === 'invitee' && $mission['statut'] === 'ouverte' ? 'element-non-lu' : '' ?>">
                    <div>
                        <p class="membre-nom">
                            <a href="<?= e(url('modules/listings/voir.php?id=' . (int) $mission['id'])) ?>"><?= e($mission['titre']) ?></a>
                        </p>
                        <p class="membre-info">
                            <?= e(dates_mission($mission)) ?> · par <?= e($mission['auteur_prenom'] . ' ' . $mission['auteur_nom']) ?>
                        </p>
                    </div>
                    <ul class="liste-etiquettes">
                        <?php if ($mission['statut'] === 'annulee'): ?>
                            <li><span class="etiquette etiquette-refusee">Mission annulée</span></li>
                        <?php else: ?>
                            <li><?= etiquette_candidature($mission['statut_candidature']) ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($mission['statut_candidature'] === 'invitee' && $mission['statut'] === 'ouverte'): ?>
                        <div class="groupe-boutons">
                            <?= bouton_action('accepter_invitation', ['id' => (int) $mission['candidature_id']], 'Accepter', 'bouton bouton-succes bouton-petit') ?>
                            <?= bouton_action('refuser_invitation', ['id' => (int) $mission['candidature_id']], 'Refuser', 'bouton bouton-secondaire bouton-petit') ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
