<?php
/* ==========================================================================
   KRAB — index.php
   --------------------------------------------------------------------------
   Page d'accueil du site.

   Toutes les pages suivent le même modèle :
     1. initialisation (configuration, sécurité, session) ;
     2. titre de la page et lien actif du menu ;
     3. en-tête commun ;
     4. contenu propre à la page ;
     5. pied de page commun.
   ========================================================================== */

// 1. Initialisation
require __DIR__ . '/includes/initialisation.php';

// 2. Informations de la page
$titre_page  = 'Accueil';
$page_active = 'accueil';

// 3. En-tête commun
require __DIR__ . '/includes/entete.php';
?>

<!-- 4. Contenu de la page -->
<div class="entete-page">
    <?php if (est_connecte()): ?>
        <h1>Bonjour <?= e(membre_connecte()['prenom']) ?></h1>
    <?php else: ?>
        <h1>Bienvenue sur KRAB</h1>
    <?php endif; ?>
    <p class="texte-discret"><?= e(SITE_DESCRIPTION) ?></p>
</div>

<?php if (!est_connecte()): ?>
    <div class="groupe-boutons">
        <a class="bouton" href="<?= e(url('modules/members/inscription.php')) ?>">Rejoindre KRAB</a>
        <a class="bouton bouton-secondaire" href="<?= e(url('modules/members/connexion.php')) ?>">Se connecter</a>
    </div>
<?php endif; ?>

<section class="section texte-lisible">
    <p>
        KRAB est un espace d'entraide entre professionnels :
        chacun peut proposer ses services, trouver du travail,
        publier des missions et organiser son agenda.
    </p>
    <p>
        Tous les membres ont le même statut et les mêmes droits.
    </p>
</section>

<section class="section">
    <div class="grille grille-3">
        <article class="carte">
            <h2 class="carte-titre">Proposer</h2>
            <p>Faites connaître vos compétences et vos disponibilités.</p>
        </article>
        <article class="carte">
            <h2 class="carte-titre">Trouver</h2>
            <p>Répondez aux missions publiées par les autres membres.</p>
        </article>
        <article class="carte">
            <h2 class="carte-titre">Coopérer</h2>
            <p>Constituez des équipes et organisez-vous ensemble.</p>
        </article>
    </div>
</section>

<?php
// 5. Pied de page commun
require __DIR__ . '/includes/pied-page.php';
