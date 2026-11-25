<?php
/* ==========================================================================
   KRAB — modules/listings/publier.php
   --------------------------------------------------------------------------
   Publier une nouvelle mission, ou modifier une mission existante.

     publier.php        → nouvelle mission
     publier.php?id=12  → modifier la mission 12 (seulement par son auteur)
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi = membre_connecte();

// ---- Nouvelle mission ou modification ? ---------------------------------
$id = (int) ($_GET['id'] ?? 0);
$mission = null;

if ($id > 0) {
    $mission = lire_mission($id);

    // Seul l'auteur peut modifier sa mission (contrôle côté serveur)
    if (!$mission || (int) $mission['auteur_id'] !== (int) $moi['id']) {
        http_response_code(403);
        exit('Vous ne pouvez pas modifier cette mission.');
    }
    if ($mission['statut'] === 'annulee') {
        message_flash('alerte', 'Une mission annulée ne peut plus être modifiée.');
        rediriger('modules/listings/voir.php?id=' . $id);
    }
}

// ---- Valeurs du formulaire ----------------------------------------------
if ($mission) {
    $requete = bdd()->prepare('SELECT membre_id FROM missions_cibles WHERE mission_id = :id');
    $requete->execute(['id' => $id]);
    $valeurs = [
        'titre'         => $mission['titre'],
        'description'   => $mission['description'],
        'competence_id' => (int) $mission['competence_id'],
        'localite'      => $mission['localite'] ?? '',
        'canton'        => $mission['canton'] ?? '',
        'date_debut'    => $mission['date_debut'],
        'date_fin'      => $mission['date_fin'] ?? '',
        'nombre_places' => (int) $mission['nombre_places'],
        'visibilite'    => $mission['visibilite'],
        'cibles'        => array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN)),
    ];
} else {
    $valeurs = [
        'titre' => '', 'description' => '', 'competence_id' => 0, 'localite' => '',
        'canton' => '', 'date_debut' => '', 'date_fin' => '', 'nombre_places' => 1,
        'visibilite' => 'publique', 'cibles' => [],
    ];
}
$erreurs = [];

// Les autres membres (pour choisir qui voit une mission « ciblée »)
$requete = bdd()->prepare('SELECT id, prenom, nom FROM membres WHERE id <> :moi ORDER BY nom, prenom');
$requete->execute(['moi' => $moi['id']]);
$autres_membres = $requete->fetchAll();
$ids_membres    = array_map('intval', array_column($autres_membres, 'id'));

$liste_competences = toutes_les_competences();


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
   -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verifier();

    $valeurs['titre']         = trim((string) ($_POST['titre'] ?? ''));
    $valeurs['description']   = trim((string) ($_POST['description'] ?? ''));
    $valeurs['competence_id'] = (int) ($_POST['competence_id'] ?? 0);
    $valeurs['localite']      = trim((string) ($_POST['localite'] ?? ''));
    $valeurs['canton']        = (string) ($_POST['canton'] ?? '');
    $valeurs['date_debut']    = (string) ($_POST['date_debut'] ?? '');
    $valeurs['date_fin']      = (string) ($_POST['date_fin'] ?? '');
    $valeurs['nombre_places'] = (int) ($_POST['nombre_places'] ?? 1);
    $valeurs['visibilite']    = (string) ($_POST['visibilite'] ?? '');

    // Membres choisis : uniquement des membres existants (et pas soi-même)
    $cibles = is_array($_POST['cibles'] ?? null) ? $_POST['cibles'] : [];
    $valeurs['cibles'] = array_values(array_intersect(array_map('intval', $cibles), $ids_membres));

    // ---- Vérifications ----
    if ($valeurs['titre'] === '') {
        $erreurs['titre'] = 'Donnez un titre à la mission.';
    } elseif (mb_strlen($valeurs['titre']) > 150) {
        $erreurs['titre'] = 'Le titre est trop long (150 caractères maximum).';
    }

    if ($valeurs['description'] === '') {
        $erreurs['description'] = 'Décrivez la mission.';
    } elseif (mb_strlen($valeurs['description']) > 5000) {
        $erreurs['description'] = 'La description est trop longue (5000 caractères maximum).';
    }

    if ($valeurs['competence_id'] !== 0
        && !in_array($valeurs['competence_id'], array_map('intval', array_column($liste_competences, 'id')), true)) {
        $erreurs['competence_id'] = 'Choisissez une compétence dans la liste.';
    }

    if (mb_strlen($valeurs['localite']) > 100) {
        $erreurs['localite'] = 'Le nom de la localité est trop long.';
    }
    if ($valeurs['canton'] !== '' && nom_canton($valeurs['canton']) === '') {
        $erreurs['canton'] = 'Choisissez un canton dans la liste.';
    }

    // Une date valide a la forme AAAA-MM-JJ (c'est ce qu'envoie le champ "date")
    $date_valide = fn(string $d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
                                    && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));

    if (!$date_valide($valeurs['date_debut'])) {
        $erreurs['date_debut'] = 'Indiquez la date de début.';
    } elseif (!$mission && $valeurs['date_debut'] < date('Y-m-d')) {
        $erreurs['date_debut'] = 'La date de début ne peut pas être passée.';
    }
    if ($valeurs['date_fin'] !== '') {
        if (!$date_valide($valeurs['date_fin'])) {
            $erreurs['date_fin'] = 'Cette date n\'est pas valide.';
        } elseif (!isset($erreurs['date_debut']) && $valeurs['date_fin'] < $valeurs['date_debut']) {
            $erreurs['date_fin'] = 'La date de fin doit être après la date de début.';
        }
    }

    $minimum_places = $mission ? max(1, (int) $mission['places_prises']) : 1;
    if ($valeurs['nombre_places'] < $minimum_places || $valeurs['nombre_places'] > PLACES_MAXIMUM) {
        $erreurs['nombre_places'] = $mission && $mission['places_prises'] > 1
            ? 'Il faut au moins ' . $minimum_places . ' places : ' . $minimum_places . ' personnes sont déjà confirmées.'
            : 'Indiquez un nombre entre 1 et ' . PLACES_MAXIMUM . '.';
    }

    if (!array_key_exists($valeurs['visibilite'], visibilites())) {
        $erreurs['visibilite'] = 'Choisissez qui peut voir la mission.';
    } elseif ($valeurs['visibilite'] === 'ciblee' && empty($valeurs['cibles'])) {
        $erreurs['cibles'] = 'Choisissez au moins un membre.';
    }

    // ---- Enregistrement ----
    if (empty($erreurs)) {
        $donnees = [
            'titre'         => $valeurs['titre'],
            'description'   => $valeurs['description'],
            'competence_id' => $valeurs['competence_id'] ?: null,
            'localite'      => $valeurs['localite'] !== '' ? $valeurs['localite'] : null,
            'canton'        => $valeurs['canton'] !== '' ? $valeurs['canton'] : null,
            'date_debut'    => $valeurs['date_debut'],
            'date_fin'      => $valeurs['date_fin'] !== '' ? $valeurs['date_fin'] : null,
            'nombre_places' => $valeurs['nombre_places'],
            'visibilite'    => $valeurs['visibilite'],
        ];

        $bdd = bdd();
        $bdd->beginTransaction();

        if ($mission) {
            $requete = $bdd->prepare(
                'UPDATE missions SET titre = :titre, description = :description,
                    competence_id = :competence_id, localite = :localite, canton = :canton,
                    date_debut = :date_debut, date_fin = :date_fin,
                    nombre_places = :nombre_places, visibilite = :visibilite
                 WHERE id = :id AND auteur_id = :auteur'
            );
            $requete->execute($donnees + ['id' => $id, 'auteur' => $moi['id']]);
        } else {
            $requete = $bdd->prepare(
                'INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                                       date_debut, date_fin, nombre_places, visibilite)
                 VALUES (:auteur, :titre, :description, :competence_id, :localite, :canton,
                         :date_debut, :date_fin, :nombre_places, :visibilite)'
            );
            $requete->execute($donnees + ['auteur' => $moi['id']]);
            $id = (int) $bdd->lastInsertId();
        }

        // Membres choisis : on remplace la liste (vide si la mission n'est pas « ciblée »)
        $bdd->prepare('DELETE FROM missions_cibles WHERE mission_id = :id')->execute(['id' => $id]);
        if ($valeurs['visibilite'] === 'ciblee') {
            $ajout = $bdd->prepare('INSERT INTO missions_cibles (mission_id, membre_id) VALUES (:mission, :membre)');
            foreach ($valeurs['cibles'] as $membre_id) {
                $ajout->execute(['mission' => $id, 'membre' => $membre_id]);
            }
        }

        $bdd->commit();

        message_flash('succes', $mission ? 'La mission est modifiée.' : 'La mission est publiée.');
        rediriger('modules/listings/voir.php?id=' . $id);
    }
}


/* --------------------------------------------------------------------------
   AFFICHAGE
   -------------------------------------------------------------------------- */

$titre_page  = $mission ? 'Modifier la mission' : 'Publier une mission';
$page_active = 'missions';
require __DIR__ . '/../../includes/entete.php';

$classe = fn(string $nom) => isset($erreurs[$nom]) ? 'champ champ-en-erreur' : 'champ';
$erreur = fn(string $nom) => isset($erreurs[$nom]) ? '<p class="champ-erreur">' . e($erreurs[$nom]) . '</p>' : '';
?>

<div class="entete-page">
    <h1><?= e($titre_page) ?></h1>
</div>

<?php if (!empty($erreurs)): ?>
    <div class="message message-erreur" role="alert">
        Le formulaire contient des erreurs. Corrigez les champs indiqués ci-dessous.
    </div>
<?php endif; ?>

<form class="formulaire" method="post" action="">
    <?= csrf_champ() ?>

    <div class="<?= $classe('titre') ?>">
        <label for="titre">Titre <span class="obligatoire">*</span></label>
        <input type="text" id="titre" name="titre" required maxlength="150" value="<?= e($valeurs['titre']) ?>">
        <p class="champ-aide">Ex. « Aide pour un déménagement à Sion ».</p>
        <?= $erreur('titre') ?>
    </div>

    <div class="<?= $classe('description') ?>">
        <label for="description">Description <span class="obligatoire">*</span></label>
        <textarea id="description" name="description" rows="8" required maxlength="5000"><?= e($valeurs['description']) ?></textarea>
        <p class="champ-aide">Ce qu'il faut faire, les horaires, le matériel, ce que vous proposez en retour…</p>
        <?= $erreur('description') ?>
    </div>

    <div class="<?= $classe('competence_id') ?>">
        <label for="competence_id">Compétence recherchée</label>
        <select id="competence_id" name="competence_id">
            <option value="0">— Aucune en particulier —</option>
            <?php foreach ($liste_competences as $competence): ?>
                <option value="<?= (int) $competence['id'] ?>" <?= $valeurs['competence_id'] === (int) $competence['id'] ? 'selected' : '' ?>>
                    <?= e($competence['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= $erreur('competence_id') ?>
    </div>

    <div class="champs-cote-a-cote">
        <div class="<?= $classe('localite') ?>">
            <label for="localite">Localité</label>
            <input type="text" id="localite" name="localite" maxlength="100" value="<?= e($valeurs['localite']) ?>">
            <p class="champ-aide">Ou « À distance ».</p>
            <?= $erreur('localite') ?>
        </div>
        <div class="<?= $classe('canton') ?>">
            <label for="canton">Canton</label>
            <select id="canton" name="canton">
                <option value="">— Choisir —</option>
                <?php foreach (cantons() as $code => $nom): ?>
                    <option value="<?= e($code) ?>" <?= $valeurs['canton'] === $code ? 'selected' : '' ?>><?= e($nom) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $erreur('canton') ?>
        </div>
    </div>

    <div class="champs-cote-a-cote">
        <div class="<?= $classe('date_debut') ?>">
            <label for="date_debut">Date de début <span class="obligatoire">*</span></label>
            <input type="date" id="date_debut" name="date_debut" required value="<?= e($valeurs['date_debut']) ?>">
            <?= $erreur('date_debut') ?>
        </div>
        <div class="<?= $classe('date_fin') ?>">
            <label for="date_fin">Date de fin</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= e($valeurs['date_fin']) ?>">
            <p class="champ-aide">Laisser vide pour une seule journée.</p>
            <?= $erreur('date_fin') ?>
        </div>
    </div>

    <div class="<?= $classe('nombre_places') ?>">
        <label for="nombre_places">Nombre de personnes recherchées <span class="obligatoire">*</span></label>
        <input type="number" id="nombre_places" name="nombre_places" required
               min="1" max="<?= PLACES_MAXIMUM ?>" value="<?= (int) $valeurs['nombre_places'] ?>">
        <?= $erreur('nombre_places') ?>
    </div>

    <fieldset class="groupe-choix <?= isset($erreurs['visibilite']) ? 'champ-en-erreur' : '' ?>">
        <legend>Qui peut voir cette mission ? <span class="obligatoire">*</span></legend>
        <?php foreach (visibilites() as $code => $texte): ?>
            <label class="choix">
                <input type="radio" name="visibilite" value="<?= e($code) ?>" <?= $valeurs['visibilite'] === $code ? 'checked' : '' ?>>
                <?= e($texte) ?>
            </label>
        <?php endforeach; ?>
        <?= $erreur('visibilite') ?>
    </fieldset>

    <div class="<?= $classe('cibles') ?>" id="choix-cibles">
        <label for="cibles">Membres choisis</label>
        <select id="cibles" name="cibles[]" multiple size="8">
            <?php foreach ($autres_membres as $autre): ?>
                <option value="<?= (int) $autre['id'] ?>" <?= in_array((int) $autre['id'], $valeurs['cibles'], true) ? 'selected' : '' ?>>
                    <?= e($autre['nom'] . ' ' . $autre['prenom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="champ-aide">Seulement si vous avez choisi « Membres choisis ». Maintenez la touche ⌘ (Cmd) pour en sélectionner plusieurs.</p>
        <?= $erreur('cibles') ?>
    </div>

    <div class="actions-formulaire">
        <button class="bouton" type="submit"><?= $mission ? 'Enregistrer' : 'Publier la mission' ?></button>
        <a href="<?= e(url($mission ? 'modules/listings/voir.php?id=' . $id : 'modules/listings/mes-missions.php')) ?>">Annuler</a>
    </div>
</form>

<?php
require __DIR__ . '/../../includes/pied-page.php';
