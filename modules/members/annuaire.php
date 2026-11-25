<?php
/* ==========================================================================
   KRAB — modules/members/annuaire.php
   --------------------------------------------------------------------------
   Liste des membres, réservée aux membres connectés.

   - Filtres : par compétence et par canton.
   - Tri : simplement par ordre alphabétique (nom, puis prénom).
     Aucun classement, aucune mise en avant : tous les membres sont égaux.
   - 20 membres par page.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_connexion();

const MEMBRES_PAR_PAGE = 20;

// Filtres choisis (dans l'adresse de la page, ex. annuaire.php?competence=4&canton=VD)
$filtre_competence = (int) ($_GET['competence'] ?? 0);
$filtre_canton     = (string) ($_GET['canton'] ?? '');
if (nom_canton($filtre_canton) === '') {
    $filtre_canton = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));


/* --------------------------------------------------------------------------
   Construction de la requête selon les filtres.
   Les conditions sont écrites par nous ; les valeurs choisies par le
   visiteur passent uniquement par des marqueurs (:competence, :canton).
   -------------------------------------------------------------------------- */

$conditions = [];
$parametres = [];

if ($filtre_competence > 0) {
    $conditions[] = 'm.id IN (SELECT membre_id FROM membres_competences WHERE competence_id = :competence)';
    $parametres['competence'] = $filtre_competence;
}
if ($filtre_canton !== '') {
    $conditions[] = 'm.canton = :canton';
    $parametres['canton'] = $filtre_canton;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Nombre total de membres trouvés (pour la pagination)
$requete = bdd()->prepare("SELECT COUNT(*) FROM membres m $where");
$requete->execute($parametres);
$total = (int) $requete->fetchColumn();

$nombre_pages = max(1, (int) ceil($total / MEMBRES_PAR_PAGE));
$page = min($page, $nombre_pages);
$decalage = ($page - 1) * MEMBRES_PAR_PAGE;

// Membres de la page actuelle (LIMIT et OFFSET sont des nombres calculés par nous)
$requete = bdd()->prepare(
    "SELECT m.id, m.prenom, m.nom, m.localite, m.canton, m.photo
     FROM membres m
     $where
     ORDER BY m.nom, m.prenom
     LIMIT " . MEMBRES_PAR_PAGE . " OFFSET " . (int) $decalage
);
$requete->execute($parametres);
$membres = $requete->fetchAll();

// Compétences de tous les membres affichés, en une seule requête
$competences_par_membre = [];
if ($membres) {
    $ids = array_column($membres, 'id');
    $marqueurs = implode(',', array_fill(0, count($ids), '?'));   // ?,?,?...
    $requete = bdd()->prepare(
        "SELECT mc.membre_id, c.nom
         FROM membres_competences mc
         JOIN competences c ON c.id = mc.competence_id
         WHERE mc.membre_id IN ($marqueurs)
         ORDER BY c.nom"
    );
    $requete->execute($ids);
    foreach ($requete->fetchAll() as $ligne) {
        $competences_par_membre[$ligne['membre_id']][] = $ligne['nom'];
    }
}

/** Adresse de l'annuaire en gardant les filtres, pour la pagination. */
function adresse_page(int $numero, int $competence, string $canton): string
{
    $parametres = array_filter(['competence' => $competence ?: null, 'canton' => $canton ?: null, 'page' => $numero]);
    return url('modules/members/annuaire.php?' . http_build_query($parametres));
}


$titre_page  = 'Membres';
$page_active = 'membres';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Membres</h1>
    <p class="texte-discret"><?= $total ?> membre<?= $total > 1 ? 's' : '' ?></p>
</div>

<!-- Filtres -->
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
            <a href="<?= e(url('modules/members/annuaire.php')) ?>">Effacer les filtres</a>
        <?php endif; ?>
    </div>
</form>

<section class="section">
    <?php if (!$membres): ?>
        <div class="etat-vide">
            <p>Aucun membre ne correspond à ces critères.</p>
        </div>
    <?php else: ?>
        <ul class="liste-elements">
            <?php foreach ($membres as $membre):
                $lieu = implode(' · ', array_filter([$membre['localite'] ?? '', nom_canton($membre['canton'])]));
            ?>
                <li class="element">
                    <div class="membre-resume">
                        <?= avatar($membre) ?>
                        <div>
                            <p class="membre-nom">
                                <a href="<?= e(url('modules/members/profil.php?id=' . (int) $membre['id'])) ?>">
                                    <?= e($membre['prenom'] . ' ' . $membre['nom']) ?>
                                </a>
                            </p>
                            <?php if ($lieu !== ''): ?>
                                <p class="membre-info"><?= e($lieu) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($competences_par_membre[$membre['id']])): ?>
                        <ul class="liste-etiquettes">
                            <?php foreach ($competences_par_membre[$membre['id']] as $nom): ?>
                                <li><span class="etiquette"><?= e($nom) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($nombre_pages > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($page > 1): ?>
            <a href="<?= e(adresse_page($page - 1, $filtre_competence, $filtre_canton)) ?>">Précédent</a>
        <?php endif; ?>
        <?php for ($numero = 1; $numero <= $nombre_pages; $numero++): ?>
            <?php if ($numero === $page): ?>
                <span class="actif" aria-current="page"><?= $numero ?></span>
            <?php else: ?>
                <a href="<?= e(adresse_page($numero, $filtre_competence, $filtre_canton)) ?>"><?= $numero ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $nombre_pages): ?>
            <a href="<?= e(adresse_page($page + 1, $filtre_competence, $filtre_canton)) ?>">Suivant</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php
require __DIR__ . '/../../includes/pied-page.php';
