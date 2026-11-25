<?php
/* ==========================================================================
   KRAB — modules/listings/mes-missions.php
   --------------------------------------------------------------------------
   Les missions que j'ai publiées, avec le nombre de candidatures
   en attente (à traiter) et de places prises.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi = membre_connecte();

$requete = bdd()->prepare(
    "SELECT mi.*,
            (SELECT COUNT(*) FROM candidatures ca WHERE ca.mission_id = mi.id AND ca.statut = 'acceptee')   AS places_prises,
            (SELECT COUNT(*) FROM candidatures ca WHERE ca.mission_id = mi.id AND ca.statut = 'en_attente') AS en_attente
     FROM missions mi
     WHERE mi.auteur_id = :moi
     ORDER BY mi.date_debut DESC"
);
$requete->execute(['moi' => $moi['id']]);
$missions = $requete->fetchAll();

$titre_page   = 'Mes missions';
$page_active  = 'missions';
$onglet_actif = 'mes-missions';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Missions</h1>
</div>

<div class="groupe-boutons">
    <a class="bouton" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
</div>

<?php require __DIR__ . '/_onglets.php'; ?>

<section class="section">
    <?php if (!$missions): ?>
        <div class="etat-vide">
            <p>Vous n'avez encore publié aucune mission.</p>
            <a class="bouton bouton-secondaire" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
        </div>
    <?php else: ?>
        <ul class="liste-elements">
            <?php foreach ($missions as $mission): ?>
                <li class="element <?= $mission['en_attente'] > 0 ? 'element-non-lu' : '' ?>">
                    <div>
                        <p class="membre-nom">
                            <a href="<?= e(url('modules/listings/voir.php?id=' . (int) $mission['id'])) ?>"><?= e($mission['titre']) ?></a>
                        </p>
                        <p class="membre-info">
                            <?= e(dates_mission($mission)) ?>
                            · <?= (int) $mission['places_prises'] ?>/<?= (int) $mission['nombre_places'] ?> confirmée<?= $mission['places_prises'] > 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <ul class="liste-etiquettes">
                        <?php if ($mission['statut'] === 'annulee'): ?>
                            <li><span class="etiquette etiquette-refusee">Annulée</span></li>
                        <?php else: ?>
                            <li><?= etiquette_visibilite($mission['visibilite']) ?></li>
                        <?php endif; ?>
                        <?php if ($mission['en_attente'] > 0): ?>
                            <li><span class="etiquette etiquette-attente"><?= (int) $mission['en_attente'] ?> à traiter</span></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
