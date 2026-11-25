<?php
/* ==========================================================================
   KRAB — modules/agenda/_grille.php
   --------------------------------------------------------------------------
   Affichage d'un mois : navigation, grille (ordinateur / tablette) et
   liste des jours (téléphone). Utilisé par l'agenda personnel et par
   l'agenda d'un autre membre.

   Avant de l'inclure, la page définit :
     $mois         → premier jour du mois (DateTimeImmutable)
     $evenements   → ['AAAA-MM-JJ' => [ ['type' => 'dispo', 'texte' => '...', 'lien' => '...'], ... ]]
                     type : 'dispo', 'confirmee', 'attente', 'ma-mission', 'proposee' ou 'filigrane'
                     Au lieu d'un lien, un élément peut contenir 'action' : un bouton qui
                     envoie un formulaire (ex. inviter un membre), avec 'confirmation'.
     $adresse_mois → fonction qui reçoit 'AAAA-MM' et renvoie l'adresse de ce mois
     $lien_ajout   → true pour que chaque numéro de jour permette d'ajouter
                     une disponibilité (agenda personnel uniquement)
     $vue          → 'mois' (grille) ou 'liste' (facultatif, 'mois' par défaut)
   ========================================================================== */

$vue         = $vue ?? 'mois';
$aujourdhui  = date('Y-m-d');
$mois_actuel = $mois->format('Y-m');
$noms_jours  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$jours_longs = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

/** Un élément de l'agenda : bouton d'action, lien, ou simple texte. */
$afficher_evenement = function (array $evenement): string {
    $classes = 'agenda-evenement agenda-evenement-' . $evenement['type'];

    // Bouton qui envoie un formulaire protégé (ex. inviter un membre sur ma mission).
    // data-confirmation : confirmation.js demande « Êtes-vous sûr ? » avant l'envoi.
    if (!empty($evenement['action'])) {
        $html = '<form method="post" action="' . e(url('modules/applications/action.php')) . '"'
              . ' data-confirmation="' . e($evenement['confirmation'] ?? '') . '">'
              . csrf_champ();
        foreach ($evenement['action'] as $nom => $valeur) {
            $html .= '<input type="hidden" name="' . e($nom) . '" value="' . e((string) $valeur) . '">';
        }
        return $html . '<button class="' . $classes . '" type="submit" title="' . e($evenement['texte']) . '">'
                     . e($evenement['texte']) . '</button></form>';
    }

    if (!empty($evenement['lien'])) {
        return '<a class="' . $classes . '" href="' . e($evenement['lien']) . '" title="' . e($evenement['texte']) . '">'
             . e($evenement['texte']) . '</a>';
    }
    return '<span class="' . $classes . '">' . e($evenement['texte']) . '</span>';
};
?>

<nav class="agenda-navigation" aria-label="Changer de mois">
    <a href="<?= e($adresse_mois($mois->modify('-1 month')->format('Y-m'))) ?>">← Mois précédent</a>
    <h2 class="agenda-mois"><?= e(nom_mois((int) $mois->format('n')) . ' ' . $mois->format('Y')) ?></h2>
    <a href="<?= e($adresse_mois($mois->modify('+1 month')->format('Y-m'))) ?>">Mois suivant →</a>
</nav>

<?php if ($vue === 'mois'): ?>
<!-- Grille du mois (ordinateur et tablette ; remplacée par la liste sur téléphone) -->
<table class="agenda-grille">
    <thead>
        <tr>
            <?php foreach ($noms_jours as $nom): ?>
                <th scope="col"><?= e($nom) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach (semaines_du_mois($mois) as $semaine): ?>
            <tr>
                <?php foreach ($semaine as $jour):
                    $classes = 'agenda-jour';
                    if (substr($jour, 0, 7) !== $mois_actuel) { $classes .= ' agenda-jour-autre-mois'; }
                    if ($jour === $aujourdhui)                 { $classes .= ' agenda-jour-aujourdhui'; }
                    $numero = (int) substr($jour, 8, 2);
                ?>
                    <td class="<?= $classes ?>">
                        <?php if (!empty($lien_ajout) && $jour >= $aujourdhui): ?>
                            <a class="agenda-numero" href="<?= e(url('modules/agenda/disponibilite.php?date=' . $jour)) ?>"
                               title="Ajouter une disponibilité le <?= e(date('d.m.Y', strtotime($jour))) ?>"><?= $numero ?></a>
                        <?php else: ?>
                            <span class="agenda-numero"><?= $numero ?></span>
                        <?php endif; ?>

                        <?php if (!empty($evenements[$jour])): ?>
                            <ul class="agenda-evenements">
                                <?php foreach ($evenements[$jour] as $evenement): ?>
                                    <li><?= $afficher_evenement($evenement) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Liste des jours : vue « liste », ou sur téléphone. Seulement les jours du mois qui ont quelque chose. -->
<div class="agenda-liste<?= $vue === 'liste' ? ' agenda-liste-visible' : '' ?>">
    <?php
    $jours_du_mois = array_filter(array_keys($evenements), fn($jour) => substr($jour, 0, 7) === $mois_actuel);
    sort($jours_du_mois);
    ?>
    <?php if (!$jours_du_mois): ?>
        <div class="etat-vide"><p>Rien de prévu ce mois-ci.</p></div>
    <?php endif; ?>
    <?php foreach ($jours_du_mois as $jour): ?>
        <section class="section">
            <h3 class="agenda-liste-jour">
                <?= e($jours_longs[(int) date('N', strtotime($jour)) - 1] . ' ' . date('d.m.Y', strtotime($jour))) ?>
            </h3>
            <ul class="agenda-evenements">
                <?php foreach ($evenements[$jour] as $evenement): ?>
                    <li><?= $afficher_evenement($evenement) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
