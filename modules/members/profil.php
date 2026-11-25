<?php
/* ==========================================================================
   KRAB — modules/members/profil.php
   --------------------------------------------------------------------------
   Page de profil d'un membre. Réservée aux membres connectés.

   Adresse :  profil.php?id=12   (sans id : son propre profil)
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_connexion();

$moi = membre_connecte();
$id  = (int) ($_GET['id'] ?? $moi['id']);

$requete = bdd()->prepare(
    'SELECT id, prenom, nom, presentation, localite, canton, photo, date_inscription
     FROM membres
     WHERE id = :id'
);
$requete->execute(['id' => $id]);
$membre = $requete->fetch();

if (!$membre) {
    http_response_code(404);
    $titre_page = 'Membre introuvable';
    require __DIR__ . '/../../includes/entete.php';
    echo '<div class="etat-vide"><p>Ce membre n\'existe pas ou a quitté KRAB.</p>'
       . '<a class="bouton bouton-secondaire" href="' . e(url('modules/members/annuaire.php')) . '">Voir les membres</a></div>';
    require __DIR__ . '/../../includes/pied-page.php';
    exit;
}

$competences  = competences_du_membre($membre['id']);
$est_mon_profil = ((int) $membre['id'] === (int) $moi['id']);

// Lieu : « Lausanne · Vaud », « Lausanne » ou « Vaud »
$lieu = implode(' · ', array_filter([$membre['localite'] ?? '', nom_canton($membre['canton'])]));

$titre_page  = $membre['prenom'] . ' ' . $membre['nom'];
$page_active = $est_mon_profil ? 'mon-profil' : 'membres';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <div class="membre-resume">
        <?= avatar($membre, true) ?>
        <div>
            <h1><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></h1>
            <?php if ($lieu !== ''): ?>
                <p class="membre-info"><?= e($lieu) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="groupe-boutons">
    <?php if ($est_mon_profil): ?>
        <a class="bouton" href="<?= e(url('modules/members/modifier-profil.php')) ?>">Modifier mon profil</a>
    <?php else: ?>
        <a class="bouton" href="<?= e(url('modules/agenda/membre.php?id=' . (int) $membre['id'])) ?>">Voir ses disponibilités / l'inviter</a>
    <?php endif; ?>
</div>

<section class="section">
    <h2>Compétences</h2>
    <?php if ($competences): ?>
        <ul class="liste-etiquettes">
            <?php foreach ($competences as $competence): ?>
                <li><span class="etiquette"><?= e($competence['nom']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="texte-discret">Aucune compétence indiquée.</p>
    <?php endif; ?>
</section>

<section class="section texte-lisible">
    <h2>Présentation</h2>
    <?php if (!empty($membre['presentation'])): ?>
        <?php // nl2br : garde les retours à la ligne du texte ; e() d'abord pour la sécurité ?>
        <p><?= nl2br(e($membre['presentation'])) ?></p>
    <?php else: ?>
        <p class="texte-discret">Pas encore de présentation.</p>
    <?php endif; ?>
</section>

<section class="section">
    <p class="texte-discret texte-petit">
        Membre depuis le <?= e(date('d.m.Y', strtotime($membre['date_inscription']))) ?>
    </p>
</section>

<?php
require __DIR__ . '/../../includes/pied-page.php';
