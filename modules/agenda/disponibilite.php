<?php
/* ==========================================================================
   KRAB — modules/agenda/disponibilite.php
   --------------------------------------------------------------------------
   Ajouter, modifier ou supprimer une de MES disponibilités.

     disponibilite.php                  → nouveau créneau
     disponibilite.php?date=2026-12-03  → nouveau créneau ce jour-là
     disponibilite.php?id=12            → modifier / supprimer le créneau 12

   Chaque créneau a sa propre visibilité : tous les membres,
   membres choisis, ou privé.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/fonctions.php';

exiger_connexion();
$moi_id = (int) membre_connecte()['id'];

// ---- Créneau existant ? ---------------------------------------------------
$id = (int) ($_GET['id'] ?? 0);
$dispo = null;

if ($id > 0) {
    // On ne cherche QUE parmi mes créneaux (contrôle côté serveur)
    $requete = bdd()->prepare('SELECT * FROM disponibilites WHERE id = :id AND membre_id = :moi');
    $requete->execute(['id' => $id, 'moi' => $moi_id]);
    $dispo = $requete->fetch();
    if (!$dispo) {
        http_response_code(404);
        exit('Disponibilité introuvable.');
    }
}

// ---- Valeurs du formulaire ----------------------------------------------
if ($dispo) {
    $requete = bdd()->prepare('SELECT membre_id FROM disponibilites_cibles WHERE disponibilite_id = :id');
    $requete->execute(['id' => $id]);
    $valeurs = [
        'date_jour'   => $dispo['date_jour'],
        'heure_debut' => heure_courte($dispo['heure_debut']),
        'heure_fin'   => heure_courte($dispo['heure_fin']),
        'visibilite'  => $dispo['visibilite'],
        'cibles'      => array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN)),
    ];
} else {
    $date_proposee = (string) ($_GET['date'] ?? '');
    $valeurs = [
        'date_jour'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_proposee) ? $date_proposee : date('Y-m-d'),
        'heure_debut' => '08:00',
        'heure_fin'   => '17:00',
        'visibilite'  => 'membres',
        'cibles'      => [],
    ];
}
$erreurs = [];

// Les autres membres (pour un créneau « membres choisis »)
$requete = bdd()->prepare('SELECT id, prenom, nom FROM membres WHERE id <> :moi ORDER BY nom, prenom');
$requete->execute(['moi' => $moi_id]);
$autres_membres = $requete->fetchAll();
$ids_membres    = array_map('intval', array_column($autres_membres, 'id'));


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
   -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verifier();

    // ---- Suppression ----
    if ($dispo && !empty($_POST['supprimer'])) {
        $requete = bdd()->prepare('DELETE FROM disponibilites WHERE id = :id AND membre_id = :moi');
        $requete->execute(['id' => $id, 'moi' => $moi_id]);
        message_flash('info', 'La disponibilité est supprimée.');
        rediriger('modules/agenda/index.php?mois=' . substr($dispo['date_jour'], 0, 7));
    }

    $valeurs['date_jour']   = (string) ($_POST['date_jour'] ?? '');
    $valeurs['heure_debut'] = (string) ($_POST['heure_debut'] ?? '');
    $valeurs['heure_fin']   = (string) ($_POST['heure_fin'] ?? '');
    $valeurs['visibilite']  = (string) ($_POST['visibilite'] ?? '');
    $cibles = is_array($_POST['cibles'] ?? null) ? $_POST['cibles'] : [];
    $valeurs['cibles'] = array_values(array_intersect(array_map('intval', $cibles), $ids_membres));

    // ---- Vérifications ----
    $d = $valeurs['date_jour'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))) {
        $erreurs['date_jour'] = 'Indiquez une date valide.';
    } elseif (!$dispo && $d < date('Y-m-d')) {
        $erreurs['date_jour'] = 'La date ne peut pas être passée.';
    }

    $heure_valide = fn(string $h) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
    if (!$heure_valide($valeurs['heure_debut'])) {
        $erreurs['heure_debut'] = 'Indiquez l\'heure de début.';
    }
    if (!$heure_valide($valeurs['heure_fin'])) {
        $erreurs['heure_fin'] = 'Indiquez l\'heure de fin.';
    } elseif (!isset($erreurs['heure_debut']) && $valeurs['heure_fin'] <= $valeurs['heure_debut']) {
        $erreurs['heure_fin'] = 'L\'heure de fin doit être après l\'heure de début.';
    }

    if (!array_key_exists($valeurs['visibilite'], visibilites_dispo())) {
        $erreurs['visibilite'] = 'Choisissez qui peut voir ce créneau.';
    } elseif ($valeurs['visibilite'] === 'ciblee' && empty($valeurs['cibles'])) {
        $erreurs['cibles'] = 'Choisissez au moins un membre.';
    }

    // ---- Enregistrement ----
    if (empty($erreurs)) {
        $bdd = bdd();
        $bdd->beginTransaction();

        $donnees = [
            'date_jour'   => $valeurs['date_jour'],
            'heure_debut' => $valeurs['heure_debut'],
            'heure_fin'   => $valeurs['heure_fin'],
            'visibilite'  => $valeurs['visibilite'],
            'moi'         => $moi_id,
        ];

        if ($dispo) {
            $requete = $bdd->prepare(
                'UPDATE disponibilites SET date_jour = :date_jour, heure_debut = :heure_debut,
                        heure_fin = :heure_fin, visibilite = :visibilite
                 WHERE id = :id AND membre_id = :moi'
            );
            $requete->execute($donnees + ['id' => $id]);
        } else {
            $requete = $bdd->prepare(
                'INSERT INTO disponibilites (membre_id, date_jour, heure_debut, heure_fin, visibilite)
                 VALUES (:moi, :date_jour, :heure_debut, :heure_fin, :visibilite)'
            );
            $requete->execute($donnees);
            $id = (int) $bdd->lastInsertId();
        }

        // Membres choisis : on remplace la liste
        $bdd->prepare('DELETE FROM disponibilites_cibles WHERE disponibilite_id = :id')->execute(['id' => $id]);
        if ($valeurs['visibilite'] === 'ciblee') {
            $ajout = $bdd->prepare('INSERT INTO disponibilites_cibles (disponibilite_id, membre_id) VALUES (:dispo, :membre)');
            foreach ($valeurs['cibles'] as $membre_id) {
                $ajout->execute(['dispo' => $id, 'membre' => $membre_id]);
            }
        }

        $bdd->commit();

        message_flash('succes', $dispo ? 'La disponibilité est modifiée.' : 'La disponibilité est ajoutée à votre agenda.');
        rediriger('modules/agenda/index.php?mois=' . substr($valeurs['date_jour'], 0, 7));
    }
}


/* --------------------------------------------------------------------------
   AFFICHAGE
   -------------------------------------------------------------------------- */

$titre_page  = $dispo ? 'Modifier une disponibilité' : 'Ajouter une disponibilité';
$page_active = 'agenda';
require __DIR__ . '/../../includes/entete.php';

$classe = fn(string $nom) => isset($erreurs[$nom]) ? 'champ champ-en-erreur' : 'champ';
$erreur = fn(string $nom) => isset($erreurs[$nom]) ? '<p class="champ-erreur">' . e($erreurs[$nom]) . '</p>' : '';
?>

<div class="entete-page">
    <h1><?= e($titre_page) ?></h1>
    <p class="texte-discret">Indiquez quand vous êtes libre : les auteurs de missions pourront vous inviter.</p>
</div>

<?php if (!empty($erreurs)): ?>
    <div class="message message-erreur" role="alert">
        Le formulaire contient des erreurs. Corrigez les champs indiqués ci-dessous.
    </div>
<?php endif; ?>

<form class="formulaire" method="post" action="">
    <?= csrf_champ() ?>

    <div class="<?= $classe('date_jour') ?>">
        <label for="date_jour">Jour <span class="obligatoire">*</span></label>
        <input type="date" id="date_jour" name="date_jour" required value="<?= e($valeurs['date_jour']) ?>">
        <?= $erreur('date_jour') ?>
    </div>

    <div class="champs-cote-a-cote">
        <div class="<?= $classe('heure_debut') ?>">
            <label for="heure_debut">De <span class="obligatoire">*</span></label>
            <input type="time" id="heure_debut" name="heure_debut" required value="<?= e($valeurs['heure_debut']) ?>">
            <?= $erreur('heure_debut') ?>
        </div>
        <div class="<?= $classe('heure_fin') ?>">
            <label for="heure_fin">À <span class="obligatoire">*</span></label>
            <input type="time" id="heure_fin" name="heure_fin" required value="<?= e($valeurs['heure_fin']) ?>">
            <?= $erreur('heure_fin') ?>
        </div>
    </div>

    <fieldset class="groupe-choix <?= isset($erreurs['visibilite']) ? 'champ-en-erreur' : '' ?>">
        <legend>Qui peut voir ce créneau ? <span class="obligatoire">*</span></legend>
        <?php foreach (visibilites_dispo() as $code => $texte): ?>
            <label class="choix">
                <input type="radio" name="visibilite" value="<?= e($code) ?>" <?= $valeurs['visibilite'] === $code ? 'checked' : '' ?>>
                <?= e($texte) ?>
            </label>
        <?php endforeach; ?>
        <p class="champ-aide">Un créneau privé ne sert qu'à vous organiser : personne ne pourra vous inviter dessus.</p>
        <?= $erreur('visibilite') ?>
    </fieldset>

    <div class="<?= $classe('cibles') ?>">
        <label for="cibles">Membres choisis</label>
        <select id="cibles" name="cibles[]" multiple size="8">
            <?php foreach ($autres_membres as $autre): ?>
                <option value="<?= (int) $autre['id'] ?>" <?= in_array((int) $autre['id'], $valeurs['cibles'], true) ? 'selected' : '' ?>>
                    <?= e($autre['nom'] . ' ' . $autre['prenom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="champ-aide">Seulement si vous avez choisi « Membres choisis ». Maintenez ⌘ (Cmd) pour en sélectionner plusieurs.</p>
        <?= $erreur('cibles') ?>
    </div>

    <div class="actions-formulaire">
        <button class="bouton" type="submit"><?= $dispo ? 'Enregistrer' : 'Ajouter' ?></button>
        <a href="<?= e(url('modules/agenda/index.php?mois=' . substr($valeurs['date_jour'], 0, 7))) ?>">Annuler</a>
    </div>
</form>

<?php if ($dispo): ?>
    <form class="formulaire" method="post" action="">
        <?= csrf_champ() ?>
        <input type="hidden" name="supprimer" value="1">
        <button class="bouton bouton-danger bouton-petit" type="submit">Supprimer ce créneau</button>
    </form>
<?php endif; ?>

<?php
require __DIR__ . '/../../includes/pied-page.php';
