<?php
/* ==========================================================================
   KRAB — modules/listings/_onglets.php
   --------------------------------------------------------------------------
   Onglets communs aux pages des missions.
   Avant de l'inclure, la page définit :  $onglet_actif = 'toutes';
   (valeurs : 'toutes', 'mes-missions', 'mes-candidatures')
   ========================================================================== */

$onglets = [
    'toutes'           => ['Missions proposées', 'modules/listings/index.php'],
    'mes-missions'     => ['Mes missions',       'modules/listings/mes-missions.php'],
    'mes-candidatures' => ['Candidatures et invitations', 'modules/applications/index.php'],
];
?>
<nav class="onglets" aria-label="Missions">
    <?php foreach ($onglets as $code => [$texte, $chemin]): ?>
        <?php if ($code === $onglet_actif): ?>
            <a class="onglet actif" href="<?= e(url($chemin)) ?>" aria-current="page"><?= e($texte) ?></a>
        <?php else: ?>
            <a class="onglet" href="<?= e(url($chemin)) ?>"><?= e($texte) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
