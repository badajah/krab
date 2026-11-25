<?php
/* ==========================================================================
   KRAB — modules/members/modifier-profil.php
   --------------------------------------------------------------------------
   Le membre connecté modifie SON profil : photo, compétences (3 au
   maximum), localité, canton et présentation.

   Sécurité : on modifie toujours le profil du membre connecté
   (son id vient de la session, jamais du formulaire).
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_connexion();

$moi = membre_connecte();

// Informations actuelles du profil
$requete = bdd()->prepare('SELECT presentation, localite, canton, photo FROM membres WHERE id = :id');
$requete->execute(['id' => $moi['id']]);
$profil = $requete->fetch();

$valeurs = [
    'presentation' => $profil['presentation'] ?? '',
    'localite'     => $profil['localite'] ?? '',
    'canton'       => $profil['canton'] ?? '',
    'competences'  => array_column(competences_du_membre($moi['id']), 'id'),
];
$erreurs = [];

$liste_competences = toutes_les_competences();
$ids_valides       = array_map('intval', array_column($liste_competences, 'id'));


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
   -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verifier();

    $valeurs['presentation'] = trim((string) ($_POST['presentation'] ?? ''));
    $valeurs['localite']     = trim((string) ($_POST['localite'] ?? ''));
    $valeurs['canton']       = (string) ($_POST['canton'] ?? '');

    // Compétences cochées : on ne garde que des numéros existants, sans doublon
    $cochees = is_array($_POST['competences'] ?? null) ? $_POST['competences'] : [];
    $valeurs['competences'] = array_values(array_unique(array_intersect(
        array_map('intval', $cochees),
        $ids_valides
    )));

    // Vérifications
    if (mb_strlen($valeurs['presentation']) > 2000) {
        $erreurs['presentation'] = 'La présentation est trop longue (2000 caractères maximum).';
    }
    if (mb_strlen($valeurs['localite']) > 100) {
        $erreurs['localite'] = 'Le nom de la localité est trop long.';
    }
    if ($valeurs['canton'] !== '' && nom_canton($valeurs['canton']) === '') {
        $erreurs['canton'] = 'Choisissez un canton dans la liste.';
    }
    if (count($valeurs['competences']) > COMPETENCES_MAXIMUM) {
        $erreurs['competences'] = 'Choisissez ' . COMPETENCES_MAXIMUM . ' compétences au maximum.';
    }

    // Photo : nouvelle photo envoyée ?
    $nouvelle_photo = null;
    $fichier = $_FILES['photo'] ?? null;
    if (empty($erreurs) && $fichier && $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $nouvelle_photo = enregistrer_photo($fichier);
        } catch (RuntimeException $exception) {
            $erreurs['photo'] = $exception->getMessage();
        }
    }

    if (empty($erreurs)) {

        // Quelle photo garder ?
        $photo = $profil['photo'];
        if ($nouvelle_photo !== null) {
            supprimer_photo($photo);       // l'ancienne est effacée
            $photo = $nouvelle_photo;
        } elseif (!empty($_POST['supprimer_photo'])) {
            supprimer_photo($photo);
            $photo = null;
        }

        // Une "transaction" : soit tout est enregistré, soit rien
        $bdd = bdd();
        $bdd->beginTransaction();

        $maj = $bdd->prepare(
            'UPDATE membres
             SET presentation = :presentation, localite = :localite,
                 canton = :canton, photo = :photo
             WHERE id = :id'
        );
        $maj->execute([
            'presentation' => $valeurs['presentation'] !== '' ? $valeurs['presentation'] : null,
            'localite'     => $valeurs['localite'] !== '' ? $valeurs['localite'] : null,
            'canton'       => $valeurs['canton'] !== '' ? $valeurs['canton'] : null,
            'photo'        => $photo,
            'id'           => $moi['id'],
        ]);

        // Compétences : on efface les anciennes puis on ajoute les nouvelles
        $bdd->prepare('DELETE FROM membres_competences WHERE membre_id = :id')
            ->execute(['id' => $moi['id']]);

        $ajout = $bdd->prepare(
            'INSERT INTO membres_competences (membre_id, competence_id) VALUES (:membre, :competence)'
        );
        foreach ($valeurs['competences'] as $competence_id) {
            $ajout->execute(['membre' => $moi['id'], 'competence' => $competence_id]);
        }

        $bdd->commit();

        message_flash('succes', 'Votre profil est enregistré.');
        rediriger('modules/members/profil.php');
    }
}


/* --------------------------------------------------------------------------
   AFFICHAGE
   -------------------------------------------------------------------------- */

$titre_page  = 'Modifier mon profil';
$page_active = 'mon-profil';
require __DIR__ . '/../../includes/entete.php';

$classe = fn(string $nom) => isset($erreurs[$nom]) ? 'champ champ-en-erreur' : 'champ';
?>

<div class="entete-page">
    <h1>Modifier mon profil</h1>
    <p class="texte-discret">Votre profil n'est visible que par les membres connectés.</p>
</div>

<?php if (!empty($erreurs)): ?>
    <div class="message message-erreur" role="alert">
        Le formulaire contient des erreurs. Corrigez les champs indiqués ci-dessous.
    </div>
<?php endif; ?>

<?php // enctype="multipart/form-data" : obligatoire pour envoyer un fichier ?>
<form class="formulaire" method="post" action="" enctype="multipart/form-data">
    <?= csrf_champ() ?>

    <!-- Photo -->
    <div class="<?= $classe('photo') ?>">
        <label for="photo">Photo</label>
        <div class="membre-resume">
            <?= avatar(['id' => $moi['id'], 'prenom' => $moi['prenom'], 'nom' => $moi['nom'], 'photo' => $profil['photo']]) ?>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
        </div>
        <p class="champ-aide">JPEG, PNG ou WebP, 5 Mo maximum. Facultative.</p>
        <?php if (isset($erreurs['photo'])): ?>
            <p class="champ-erreur"><?= e($erreurs['photo']) ?></p>
        <?php endif; ?>
        <?php if (!empty($profil['photo'])): ?>
            <label class="choix">
                <input type="checkbox" name="supprimer_photo" value="1"> Supprimer ma photo
            </label>
        <?php endif; ?>
    </div>

    <!-- Compétences (3 au maximum) -->
    <fieldset class="groupe-choix <?= isset($erreurs['competences']) ? 'champ-en-erreur' : '' ?>"
              data-maximum="<?= COMPETENCES_MAXIMUM ?>" id="choix-competences">
        <legend>Compétences (<?= COMPETENCES_MAXIMUM ?> au maximum)</legend>
        <div class="grille grille-3">
            <?php foreach ($liste_competences as $competence): ?>
                <label class="choix">
                    <input type="checkbox" name="competences[]" value="<?= (int) $competence['id'] ?>"
                        <?= in_array((int) $competence['id'], array_map('intval', $valeurs['competences']), true) ? 'checked' : '' ?>>
                    <?= e($competence['nom']) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($erreurs['competences'])): ?>
            <p class="champ-erreur"><?= e($erreurs['competences']) ?></p>
        <?php endif; ?>
    </fieldset>

    <!-- Lieu -->
    <div class="champs-cote-a-cote">
        <div class="<?= $classe('localite') ?>">
            <label for="localite">Localité</label>
            <input type="text" id="localite" name="localite" maxlength="100"
                   autocomplete="address-level2" value="<?= e($valeurs['localite']) ?>">
            <?php if (isset($erreurs['localite'])): ?>
                <p class="champ-erreur"><?= e($erreurs['localite']) ?></p>
            <?php endif; ?>
        </div>

        <div class="<?= $classe('canton') ?>">
            <label for="canton">Canton</label>
            <select id="canton" name="canton">
                <option value="">— Choisir —</option>
                <?php foreach (cantons() as $code => $nom): ?>
                    <option value="<?= e($code) ?>" <?= $valeurs['canton'] === $code ? 'selected' : '' ?>>
                        <?= e($nom) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($erreurs['canton'])): ?>
                <p class="champ-erreur"><?= e($erreurs['canton']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Présentation -->
    <div class="<?= $classe('presentation') ?>">
        <label for="presentation">Présentation</label>
        <textarea id="presentation" name="presentation" rows="8" maxlength="2000"><?= e($valeurs['presentation']) ?></textarea>
        <p class="champ-aide">Votre parcours, ce que vous proposez, ce que vous cherchez… 2000 caractères maximum.</p>
        <?php if (isset($erreurs['presentation'])): ?>
            <p class="champ-erreur"><?= e($erreurs['presentation']) ?></p>
        <?php endif; ?>
    </div>

    <div class="actions-formulaire">
        <button class="bouton" type="submit">Enregistrer</button>
        <a href="<?= e(url('modules/members/profil.php')) ?>">Annuler</a>
    </div>
</form>

<script src="<?= e(url('assets/js/competences.js')) ?>" defer></script>

<?php
require __DIR__ . '/../../includes/pied-page.php';
