<?php
/* ==========================================================================
   KRAB — modules/members/deconnexion.php
   --------------------------------------------------------------------------
   Déconnexion du membre.

   Pourquoi une page avec un bouton, et pas un simple lien ?
   Parce qu'un simple lien pourrait être déclenché par un autre site
   à l'insu du membre. Le bouton envoie un formulaire protégé (CSRF).
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

// Personne n'est connecté : retour à l'accueil
if (!est_connecte()) {
    rediriger('');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    deconnecter_membre();
    message_flash('info', 'Vous êtes déconnecté. À bientôt !');
    rediriger('');
}

$titre_page  = 'Déconnexion';
$page_active = 'deconnexion';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Se déconnecter</h1>
</div>

<form class="formulaire" method="post" action="">
    <?= csrf_champ() ?>
    <p>Voulez-vous vraiment vous déconnecter ?</p>
    <div class="actions-formulaire">
        <button class="bouton" type="submit">Me déconnecter</button>
        <a href="<?= e(url()) ?>">Annuler</a>
    </div>
</form>

<?php
require __DIR__ . '/../../includes/pied-page.php';
