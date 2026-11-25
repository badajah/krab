<?php
/* ==========================================================================
   KRAB — modules/members/groupes.php
   --------------------------------------------------------------------------
   Mes groupes personnels : liste et création.

   Un groupe sert à choisir rapidement plusieurs personnes quand une
   mission ou une disponibilité est « Membres choisis ».
   Mes groupes ne sont visibles que par moi.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

$nom = '';
$erreur = '';

// ---- Création d'un groupe -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    $nom = trim((string) ($_POST['nom'] ?? ''));
    if ($nom === '') {
        $erreur = 'Donnez un nom au groupe.';
    } elseif (mb_strlen($nom) > 100) {
        $erreur = 'Le nom est trop long (100 caractères maximum).';
    } else {
        $requete = bdd()->prepare('INSERT INTO groupes (proprietaire_id, nom) VALUES (:moi, :nom)');
        $requete->execute(['moi' => $moi_id, 'nom' => $nom]);
        $id = (int) bdd()->lastInsertId();

        message_flash('succes', 'Le groupe « ' . $nom . ' » est créé. Ajoutez-y des membres.');
        rediriger('modules/members/groupe.php?id=' . $id);
    }
}

$groupes = mes_groupes($moi_id);

$titre_page  = 'Mes groupes';
$page_active = 'mon-profil';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Mes groupes</h1>
    <p class="texte-discret">
        Vos groupes ne sont visibles que par vous. Ils servent à choisir rapidement
        qui voit une mission ou une disponibilité « Membres choisis ».
    </p>
</div>

<section class="section">
    <?php if (!$groupes): ?>
        <div class="etat-vide"><p>Vous n'avez pas encore de groupe.</p></div>
    <?php else: ?>
        <ul class="liste-elements">
            <?php foreach ($groupes as $groupe): ?>
                <li class="element">
                    <p class="membre-nom">
                        <a href="<?= e(url('modules/members/groupe.php?id=' . (int) $groupe['id'])) ?>"><?= e($groupe['nom']) ?></a>
                    </p>
                    <span class="texte-discret texte-petit">
                        <?= (int) $groupe['nombre_membres'] ?> membre<?= $groupe['nombre_membres'] > 1 ? 's' : '' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="section">
    <h2>Nouveau groupe</h2>
    <form class="formulaire" method="post" action="">
        <?= csrf_champ() ?>
        <div class="<?= $erreur ? 'champ champ-en-erreur' : 'champ' ?>">
            <label for="nom">Nom du groupe</label>
            <input type="text" id="nom" name="nom" required maxlength="100" value="<?= e($nom) ?>">
            <p class="champ-aide">Ex. « Équipe chantier », « Graphistes de confiance ».</p>
            <?php if ($erreur): ?><p class="champ-erreur"><?= e($erreur) ?></p><?php endif; ?>
        </div>
        <button class="bouton" type="submit">Créer le groupe</button>
    </form>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
