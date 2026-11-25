<?php
/* ==========================================================================
   KRAB — modules/listings/voir.php
   --------------------------------------------------------------------------
   Détail d'une mission.

   - L'auteur voit les candidatures reçues et peut les accepter ou refuser.
   - Les autres membres peuvent se proposer, ou suivre/retirer leur
     candidature.

   L'accès est vérifié côté serveur avec peut_voir_mission().
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi = membre_connecte();
$moi_id = (int) $moi['id'];

$mission = lire_mission((int) ($_GET['id'] ?? 0));

// Mission inexistante OU non visible : même réponse (on ne révèle pas qu'elle existe)
if (!$mission || !peut_voir_mission($mission, $moi_id)) {
    http_response_code(404);
    $titre_page  = 'Mission introuvable';
    $page_active = 'missions';
    require __DIR__ . '/../../includes/entete.php';
    echo '<div class="etat-vide"><p>Cette mission n\'existe pas ou ne vous est pas accessible.</p>'
       . '<a class="bouton bouton-secondaire" href="' . e(url('modules/listings/index.php')) . '">Voir les missions</a></div>';
    require __DIR__ . '/../../includes/pied-page.php';
    exit;
}

$est_auteur = ((int) $mission['auteur_id'] === $moi_id);

if ($est_auteur) {
    // Candidatures reçues (sauf celles retirées par leur auteur)
    $requete = bdd()->prepare(
        "SELECT ca.*, me.prenom, me.nom, me.photo
         FROM candidatures ca
         JOIN membres me ON me.id = ca.membre_id
         WHERE ca.mission_id = :id AND ca.statut <> 'retiree'
         ORDER BY FIELD(ca.statut, 'en_attente', 'acceptee', 'refusee'), ca.date_creation"
    );
    $requete->execute(['id' => $mission['id']]);
    $candidatures = $requete->fetchAll();

    // Membres choisis (mission ciblée)
    $requete = bdd()->prepare(
        'SELECT me.id, me.prenom, me.nom FROM missions_cibles mc
         JOIN membres me ON me.id = mc.membre_id
         WHERE mc.mission_id = :id ORDER BY me.nom, me.prenom'
    );
    $requete->execute(['id' => $mission['id']]);
    $cibles = $requete->fetchAll();
} else {
    $ma_candidature = candidature_de((int) $mission['id'], $moi_id);
    $refus = raison_refus_candidature($mission, $moi_id);
}

$titre_page  = $mission['titre'];
$page_active = 'missions';
require __DIR__ . '/../../includes/entete.php';

/** Petit formulaire d'action (un bouton) vers modules/applications/action.php. */
function bouton_action(string $action, int $identifiant, string $texte, string $classes = 'bouton'): string
{
    return '<form method="post" action="' . e(url('modules/applications/action.php')) . '">'
         . csrf_champ()
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<input type="hidden" name="id" value="' . $identifiant . '">'
         . '<button class="' . e($classes) . '" type="submit">' . e($texte) . '</button>'
         . '</form>';
}
?>

<div class="entete-page">
    <h1><?= e($mission['titre']) ?></h1>
    <ul class="liste-etiquettes">
        <?php if ($mission['statut'] === 'annulee'): ?>
            <li><span class="etiquette etiquette-refusee">Annulée</span></li>
        <?php elseif (mission_complete($mission)): ?>
            <li><span class="etiquette etiquette-confirmee">Complète</span></li>
        <?php endif; ?>
        <?php if ($est_auteur): ?>
            <li><?= etiquette_visibilite($mission['visibilite']) ?></li>
        <?php endif; ?>
    </ul>
</div>

<div class="avec-colonne">
    <!-- ===== Colonne : informations pratiques ===== -->
    <aside class="colonne-laterale">
        <div class="carte">
            <dl class="details">
                <dt>Dates</dt>
                <dd><?= e(dates_mission($mission)) ?></dd>

                <?php if (lieu_mission($mission) !== ''): ?>
                    <dt>Lieu</dt>
                    <dd><?= e(lieu_mission($mission)) ?></dd>
                <?php endif; ?>

                <?php if ($mission['competence_nom']): ?>
                    <dt>Compétence</dt>
                    <dd><?= e($mission['competence_nom']) ?></dd>
                <?php endif; ?>

                <dt>Places</dt>
                <dd><?= (int) $mission['places_prises'] ?> confirmée<?= $mission['places_prises'] > 1 ? 's' : '' ?> sur <?= (int) $mission['nombre_places'] ?></dd>

                <dt>Proposée par</dt>
                <dd>
                    <a href="<?= e(url('modules/members/profil.php?id=' . (int) $mission['auteur_id'])) ?>">
                        <?= e($mission['auteur_prenom'] . ' ' . $mission['auteur_nom']) ?>
                    </a>
                </dd>

                <?php if ($est_auteur && $mission['visibilite'] === 'ciblee'): ?>
                    <dt>Visible par</dt>
                    <dd><?= e(implode(', ', array_map(fn($c) => $c['prenom'] . ' ' . $c['nom'], $cibles))) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($est_auteur && $mission['statut'] === 'ouverte'): ?>
                <div class="groupe-boutons">
                    <a class="bouton bouton-secondaire bouton-petit" href="<?= e(url('modules/listings/publier.php?id=' . (int) $mission['id'])) ?>">Modifier</a>
                    <?= bouton_action('annuler_mission', (int) $mission['id'], 'Annuler la mission', 'bouton bouton-danger bouton-petit') ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="colonne-principale">
        <section class="section texte-lisible">
            <p><?= nl2br(e($mission['description'])) ?></p>
        </section>

        <?php if ($est_auteur): ?>
            <!-- ===== Vue de l'auteur : candidatures reçues ===== -->
            <section class="section">
                <h2>Candidatures</h2>
                <?php if (!$candidatures): ?>
                    <div class="etat-vide"><p>Aucune candidature pour l'instant.</p></div>
                <?php else: ?>
                    <ul class="liste-elements">
                        <?php foreach ($candidatures as $candidature): ?>
                            <li class="element <?= $candidature['statut'] === 'en_attente' ? 'element-non-lu' : '' ?>">
                                <div>
                                    <div class="membre-resume">
                                        <?= avatar(['id' => $candidature['membre_id'], 'prenom' => $candidature['prenom'],
                                                    'nom' => $candidature['nom'], 'photo' => $candidature['photo']]) ?>
                                        <div>
                                            <p class="membre-nom">
                                                <a href="<?= e(url('modules/members/profil.php?id=' . (int) $candidature['membre_id'])) ?>">
                                                    <?= e($candidature['prenom'] . ' ' . $candidature['nom']) ?>
                                                </a>
                                            </p>
                                            <p class="membre-info"><?= etiquette_candidature($candidature['statut']) ?></p>
                                        </div>
                                    </div>
                                    <?php if (!empty($candidature['message'])): ?>
                                        <p class="texte-petit"><?= nl2br(e($candidature['message'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($candidature['statut'] === 'en_attente' && $mission['statut'] === 'ouverte'): ?>
                                    <div class="groupe-boutons">
                                        <?php if (!mission_complete($mission)): ?>
                                            <?= bouton_action('accepter', (int) $candidature['id'], 'Accepter', 'bouton bouton-succes bouton-petit') ?>
                                        <?php endif; ?>
                                        <?= bouton_action('refuser', (int) $candidature['id'], 'Refuser', 'bouton bouton-secondaire bouton-petit') ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- ===== Vue d'un autre membre : se proposer ===== -->
            <section class="section">
                <h2>Ma candidature</h2>

                <?php if ($ma_candidature && in_array($ma_candidature['statut'], ['en_attente', 'acceptee'], true)): ?>
                    <p><?= etiquette_candidature($ma_candidature['statut']) ?></p>
                    <?php if ($ma_candidature['statut'] === 'acceptee'): ?>
                        <div class="message message-succes">
                            C'est confirmé : <?= e($mission['auteur_prenom']) ?> a accepté votre candidature.
                        </div>
                    <?php else: ?>
                        <p class="texte-discret">Votre proposition a été envoyée. <?= e($mission['auteur_prenom']) ?> doit encore répondre.</p>
                    <?php endif; ?>
                    <?php if ($mission['statut'] === 'ouverte'): ?>
                        <?= bouton_action('retirer', (int) $ma_candidature['id'], 'Retirer ma candidature', 'bouton bouton-discret') ?>
                    <?php endif; ?>

                <?php elseif ($ma_candidature && $ma_candidature['statut'] === 'refusee'): ?>
                    <p><?= etiquette_candidature('refusee') ?></p>
                    <p class="texte-discret">Votre candidature n'a pas été retenue pour cette mission.</p>

                <?php elseif ($refus !== ''): ?>
                    <p class="texte-discret"><?= e($refus) ?></p>

                <?php else: ?>
                    <?php // Pas encore de candidature (ou candidature retirée) : formulaire ?>
                    <form class="formulaire" method="post" action="<?= e(url('modules/applications/action.php')) ?>">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="action" value="candidater">
                        <input type="hidden" name="id" value="<?= (int) $mission['id'] ?>">
                        <div class="champ">
                            <label for="message">Message pour <?= e($mission['auteur_prenom']) ?></label>
                            <textarea id="message" name="message" rows="4" maxlength="2000"></textarea>
                            <p class="champ-aide">Facultatif : vos disponibilités, votre expérience…</p>
                        </div>
                        <button class="bouton" type="submit">Me proposer</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

</div>

<?php
require __DIR__ . '/../../includes/pied-page.php';
