<?php
/* ==========================================================================
   KRAB — modules/members/groupe.php
   --------------------------------------------------------------------------
   Gérer UN de mes groupes : renommer, ajouter ou retirer des membres,
   supprimer le groupe.

   Adresse : groupe.php?id=3

   Sécurité : on ne trouve le groupe que s'il m'appartient
   (contrôle côté serveur dans chaque requête).
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];
$id     = (int) ($_GET['id'] ?? 0);

$requete = bdd()->prepare('SELECT * FROM groupes WHERE id = :id AND proprietaire_id = :moi');
$requete->execute(['id' => $id, 'moi' => $moi_id]);
$groupe = $requete->fetch();

if (!$groupe) {
    http_response_code(404);
    exit('Groupe introuvable.');
}

$retour = 'modules/members/groupe.php?id=' . $id;

// ---- Actions ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {

        case 'renommer':
            $nom = trim((string) ($_POST['nom'] ?? ''));
            if ($nom === '' || mb_strlen($nom) > 100) {
                message_flash('erreur', 'Le nom doit contenir entre 1 et 100 caractères.');
            } else {
                $requete = bdd()->prepare('UPDATE groupes SET nom = :nom WHERE id = :id AND proprietaire_id = :moi');
                $requete->execute(['nom' => $nom, 'id' => $id, 'moi' => $moi_id]);
                message_flash('succes', 'Le groupe est renommé.');
            }
            rediriger($retour);

        case 'ajouter':
            // On n'ajoute que des membres qui existent (et pas soi-même)
            $choisis = is_array($_POST['membres'] ?? null) ? $_POST['membres'] : [];
            $valides = array_intersect(array_map('intval', $choisis),
                                       array_map('intval', array_column(autres_membres($moi_id), 'id')));
            // INSERT IGNORE : pas d'erreur si la personne est déjà dans le groupe
            $ajout = bdd()->prepare('INSERT IGNORE INTO groupes_membres (groupe_id, membre_id) VALUES (:groupe, :membre)');
            foreach ($valides as $membre_id) {
                $ajout->execute(['groupe' => $id, 'membre' => $membre_id]);
            }
            message_flash('succes', count($valides) > 1 ? 'Les membres sont ajoutés.' : 'Le membre est ajouté.');
            rediriger($retour);

        case 'retirer':
            $requete = bdd()->prepare('DELETE FROM groupes_membres WHERE groupe_id = :groupe AND membre_id = :membre');
            $requete->execute(['groupe' => $id, 'membre' => (int) ($_POST['membre_id'] ?? 0)]);
            message_flash('info', 'Le membre est retiré du groupe.');
            rediriger($retour);

        case 'supprimer':
            $requete = bdd()->prepare('DELETE FROM groupes WHERE id = :id AND proprietaire_id = :moi');
            $requete->execute(['id' => $id, 'moi' => $moi_id]);
            message_flash('info', 'Le groupe « ' . $groupe['nom'] . ' » est supprimé.');
            rediriger('modules/members/groupes.php');
    }
    rediriger($retour);
}

// ---- Membres du groupe, et membres qu'on peut ajouter ----------------------
$requete = bdd()->prepare(
    'SELECT m.id, m.prenom, m.nom, m.photo FROM groupes_membres gm
     JOIN membres m ON m.id = gm.membre_id
     WHERE gm.groupe_id = :id ORDER BY m.nom, m.prenom'
);
$requete->execute(['id' => $id]);
$dans_le_groupe = $requete->fetchAll();

$ids_dans_le_groupe = array_map('intval', array_column($dans_le_groupe, 'id'));
$a_ajouter = array_filter(autres_membres($moi_id), fn($m) => !in_array((int) $m['id'], $ids_dans_le_groupe, true));

/** Petit formulaire d'action de cette page. */
function bouton_groupe(string $action, string $texte, string $classes, array $champs = []): string
{
    $html = '<form method="post" action="">' . csrf_champ()
          . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($champs as $nom => $valeur) {
        $html .= '<input type="hidden" name="' . e($nom) . '" value="' . e((string) $valeur) . '">';
    }
    return $html . '<button class="' . e($classes) . '" type="submit">' . e($texte) . '</button></form>';
}

$titre_page  = 'Groupe ' . $groupe['nom'];
$page_active = 'mon-profil';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <p class="texte-petit"><a href="<?= e(url('modules/members/groupes.php')) ?>">← Mes groupes</a></p>
    <h1><?= e($groupe['nom']) ?></h1>
</div>

<section class="section">
    <h2>Membres du groupe</h2>
    <?php if (!$dans_le_groupe): ?>
        <div class="etat-vide"><p>Ce groupe est vide.</p></div>
    <?php else: ?>
        <ul class="liste-elements">
            <?php foreach ($dans_le_groupe as $membre): ?>
                <li class="element">
                    <div class="membre-resume">
                        <?= avatar($membre) ?>
                        <p class="membre-nom">
                            <a href="<?= e(url('modules/members/profil.php?id=' . (int) $membre['id'])) ?>"><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></a>
                        </p>
                    </div>
                    <?= bouton_groupe('retirer', 'Retirer', 'bouton bouton-discret bouton-petit', ['membre_id' => (int) $membre['id']]) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($a_ajouter): ?>
    <section class="section">
        <h2>Ajouter des membres</h2>
        <form class="formulaire" method="post" action="">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="ajouter">
            <div class="champ">
                <label for="membres">Membres</label>
                <select id="membres" name="membres[]" multiple size="8" required>
                    <?php foreach ($a_ajouter as $membre): ?>
                        <option value="<?= (int) $membre['id'] ?>"><?= e($membre['nom'] . ' ' . $membre['prenom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="champ-aide">Maintenez ⌘ (Cmd) pour en sélectionner plusieurs.</p>
            </div>
            <button class="bouton" type="submit">Ajouter au groupe</button>
        </form>
    </section>
<?php endif; ?>

<section class="section">
    <h2>Renommer</h2>
    <form class="formulaire" method="post" action="">
        <?= csrf_champ() ?>
        <input type="hidden" name="action" value="renommer">
        <div class="champ">
            <label for="nom">Nom du groupe</label>
            <input type="text" id="nom" name="nom" required maxlength="100" value="<?= e($groupe['nom']) ?>">
        </div>
        <button class="bouton bouton-secondaire" type="submit">Renommer</button>
    </form>
</section>

<section class="section">
    <h2>Supprimer le groupe</h2>
    <p class="texte-discret">
        Les membres du groupe ne verront plus les missions et disponibilités ciblées
        sur ce groupe (sauf si vous les avez aussi choisis personnellement).
    </p>
    <?= bouton_groupe('supprimer', 'Supprimer le groupe', 'bouton bouton-danger bouton-petit') ?>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
