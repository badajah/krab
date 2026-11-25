<?php
/* ==========================================================================
   KRAB — modules/listings/index.php
   --------------------------------------------------------------------------
   Missions proposées par les AUTRES membres et visibles par moi :
     - missions « Tous les membres » ;
     - missions « Membres choisis » où je fais partie des membres choisis.
   Seules les missions ouvertes et pas encore terminées sont affichées,
   par ordre de date (les plus proches d'abord).
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi = membre_connecte();

const MISSIONS_PAR_PAGE = 20;

$filtre_competence = (int) ($_GET['competence'] ?? 0);
$filtre_canton     = (string) ($_GET['canton'] ?? '');
if (nom_canton($filtre_canton) === '') {
    $filtre_canton = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

// ---- Conditions de visibilité (contrôle côté serveur) -------------------
$conditions = [
    'mi.auteur_id <> :moi',
    "mi.statut = 'ouverte'",
    'COALESCE(mi.date_fin, mi.date_debut) >= CURDATE()',
    "(mi.visibilite = 'publique'
      OR (mi.visibilite = 'ciblee'
          AND EXISTS (SELECT 1 FROM missions_cibles mc
                      WHERE mc.mission_id = mi.id AND mc.membre_id = :moi_cible)))",
];
$parametres = ['moi' => $moi['id'], 'moi_cible' => $moi['id']];

if ($filtre_competence > 0) {
    $conditions[] = 'mi.competence_id = :competence';
    $parametres['competence'] = $filtre_competence;
}
if ($filtre_canton !== '') {
    $conditions[] = 'mi.canton = :canton';
    $parametres['canton'] = $filtre_canton;
}
$where = 'WHERE ' . implode(' AND ', $conditions);

$requete = bdd()->prepare("SELECT COUNT(*) FROM missions mi $where");
$requete->execute($parametres);
$total = (int) $requete->fetchColumn();

$nombre_pages = max(1, (int) ceil($total / MISSIONS_PAR_PAGE));
$page = min($page, $nombre_pages);

$requete = bdd()->prepare(
    "SELECT mi.*, me.prenom AS auteur_prenom, me.nom AS auteur_nom, c.nom AS competence_nom,
            (SELECT COUNT(*) FROM candidatures ca
             WHERE ca.mission_id = mi.id AND ca.statut = 'acceptee') AS places_prises,
            (SELECT ca.statut FROM candidatures ca
             WHERE ca.mission_id = mi.id AND ca.membre_id = :moi_candidature) AS ma_candidature
     FROM missions mi
     JOIN membres me ON me.id = mi.auteur_id
     LEFT JOIN competences c ON c.id = mi.competence_id
     $where
     ORDER BY mi.date_debut, mi.titre
     LIMIT " . MISSIONS_PAR_PAGE . ' OFFSET ' . (($page - 1) * MISSIONS_PAR_PAGE)
);
$requete->execute($parametres + ['moi_candidature' => $moi['id']]);
$missions = $requete->fetchAll();

function adresse_page(int $numero, int $competence, string $canton): string
{
    $parametres = array_filter(['competence' => $competence ?: null, 'canton' => $canton ?: null, 'page' => $numero]);
    return url('modules/listings/index.php?' . http_build_query($parametres));
}

$titre_page   = 'Missions';
$page_active  = 'missions';
$onglet_actif = 'toutes';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Missions</h1>
</div>

<div class="groupe-boutons">
    <a class="bouton" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
</div>

<?php require __DIR__ . '/_onglets.php'; ?>

<form class="formulaire" method="get" action="">
    <div class="champs-cote-a-cote">
        <div class="champ">
            <label for="competence">Compétence</label>
            <select id="competence" name="competence">
                <option value="">Toutes</option>
                <?php foreach (toutes_les_competences() as $competence): ?>
                    <option value="<?= (int) $competence['id'] ?>" <?= $filtre_competence === (int) $competence['id'] ? 'selected' : '' ?>>
                        <?= e($competence['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="canton">Canton</label>
            <select id="canton" name="canton">
                <option value="">Tous</option>
                <?php foreach (cantons() as $code => $nom): ?>
                    <option value="<?= e($code) ?>" <?= $filtre_canton === $code ? 'selected' : '' ?>><?= e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="groupe-boutons">
        <button class="bouton bouton-secondaire" type="submit">Filtrer</button>
        <?php if ($filtre_competence || $filtre_canton): ?>
            <a href="<?= e(url('modules/listings/index.php')) ?>">Effacer les filtres</a>
        <?php endif; ?>
    </div>
</form>

<section class="section">
    <?php if (!$missions): ?>
        <div class="etat-vide">
            <p>Aucune mission proposée pour le moment.</p>
            <a class="bouton bouton-secondaire" href="<?= e(url('modules/listings/publier.php')) ?>">Publier une mission</a>
        </div>
    <?php else: ?>
        <div class="grille grille-2">
            <?php foreach ($missions as $mission): ?>
                <article class="carte">
                    <h2 class="carte-titre">
                        <a href="<?= e(url('modules/listings/voir.php?id=' . (int) $mission['id'])) ?>"><?= e($mission['titre']) ?></a>
                    </h2>
                    <p class="carte-meta">
                        <?= e(dates_mission($mission)) ?>
                        <?php if (lieu_mission($mission) !== ''): ?> · <?= e(lieu_mission($mission)) ?><?php endif; ?>
                    </p>
                    <ul class="liste-etiquettes">
                        <?php if ($mission['competence_nom']): ?>
                            <li><span class="etiquette"><?= e($mission['competence_nom']) ?></span></li>
                        <?php endif; ?>
                        <?php if ($mission['visibilite'] === 'ciblee'): ?>
                            <li><?= etiquette_visibilite('ciblee') ?></li>
                        <?php endif; ?>
                        <?php if ($mission['ma_candidature']): ?>
                            <li><?= etiquette_candidature($mission['ma_candidature']) ?></li>
                        <?php endif; ?>
                    </ul>
                    <div class="carte-pied">
                        <span class="texte-discret texte-petit">
                            Par <?= e($mission['auteur_prenom'] . ' ' . $mission['auteur_nom']) ?>
                            · <?= (int) $mission['places_prises'] ?>/<?= (int) $mission['nombre_places'] ?> place<?= $mission['nombre_places'] > 1 ? 's' : '' ?> prise<?= $mission['places_prises'] > 1 ? 's' : '' ?>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($nombre_pages > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php for ($numero = 1; $numero <= $nombre_pages; $numero++): ?>
            <?php if ($numero === $page): ?>
                <span class="actif" aria-current="page"><?= $numero ?></span>
            <?php else: ?>
                <a href="<?= e(adresse_page($numero, $filtre_competence, $filtre_canton)) ?>"><?= $numero ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php
require __DIR__ . '/../../includes/pied-page.php';
