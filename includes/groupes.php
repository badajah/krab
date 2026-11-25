<?php
/* ==========================================================================
   KRAB — includes/groupes.php
   --------------------------------------------------------------------------
   Groupes personnels et choix des « Membres choisis ».

   Une mission ou une disponibilité « Membres choisis » peut être ciblée :
     - sur des personnes (tables missions_cibles / disponibilites_cibles) ;
     - sur des groupes   (tables missions_groupes / disponibilites_groupes).

   Un membre voit l'élément s'il est choisi directement OU s'il fait
   partie d'un des groupes choisis (au moment où il regarde : les
   groupes sont « vivants »).
   ========================================================================== */


/**
 * Les tables à utiliser selon le type d'élément ciblé.
 * Liste fixe écrite par nous : jamais une valeur venant d'un visiteur.
 */
function tables_cibles(string $type): array
{
    return match ($type) {
        'mission' => ['membres' => 'missions_cibles',       'groupes' => 'missions_groupes',       'colonne' => 'mission_id'],
        'dispo'   => ['membres' => 'disponibilites_cibles', 'groupes' => 'disponibilites_groupes', 'colonne' => 'disponibilite_id'],
    };
}


/**
 * Condition SQL : « le membre :lecteur fait partie des membres choisis »
 * (choisi directement, OU membre d'un des groupes choisis).
 *
 * $type   : 'mission' ou 'dispo'
 * $champ  : colonne contenant l'identifiant de l'élément (ex. 'mi.id')
 *
 * Note : avec MySQL, un même marqueur (:lecteur) ne peut apparaître qu'UNE
 * fois par requête. D'où la forme « :lecteur IN (liste UNION liste) ».
 */
function condition_est_cible(string $type, string $champ): string
{
    $t = tables_cibles($type);
    return ":lecteur IN (SELECT cm.membre_id FROM {$t['membres']} cm
                         WHERE cm.{$t['colonne']} = $champ
                         UNION
                         SELECT gm.membre_id FROM {$t['groupes']} cg
                         JOIN groupes_membres gm ON gm.groupe_id = cg.groupe_id
                         WHERE cg.{$t['colonne']} = $champ)";
}


/** Mes groupes, avec le nombre de membres de chacun. */
function mes_groupes(int $proprietaire_id): array
{
    $requete = bdd()->prepare(
        'SELECT g.id, g.nom,
                (SELECT COUNT(*) FROM groupes_membres gm WHERE gm.groupe_id = g.id) AS nombre_membres
         FROM groupes g
         WHERE g.proprietaire_id = :moi
         ORDER BY g.nom'
    );
    $requete->execute(['moi' => $proprietaire_id]);
    return $requete->fetchAll();
}


/** Tous les autres membres (pour les listes de choix), par ordre alphabétique. */
function autres_membres(int $moi_id): array
{
    $requete = bdd()->prepare('SELECT id, prenom, nom FROM membres WHERE id <> :moi ORDER BY nom, prenom');
    $requete->execute(['moi' => $moi_id]);
    return $requete->fetchAll();
}


/** Membres et groupes choisis pour un élément : ['membres' => [ids], 'groupes' => [ids]]. */
function lire_cibles(string $type, int $id): array
{
    $t = tables_cibles($type);
    $requete = bdd()->prepare("SELECT membre_id FROM {$t['membres']} WHERE {$t['colonne']} = :id");
    $requete->execute(['id' => $id]);
    $membres = array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));

    $requete = bdd()->prepare("SELECT groupe_id FROM {$t['groupes']} WHERE {$t['colonne']} = :id");
    $requete->execute(['id' => $id]);
    $groupes = array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));

    return ['membres' => $membres, 'groupes' => $groupes];
}


/**
 * Lit les choix envoyés par le formulaire et ne garde que des valeurs
 * autorisées : des membres qui existent (pas moi), et MES groupes.
 */
function lire_choix_cibles(int $moi_id): array
{
    $membres_envoyes = is_array($_POST['cibles'] ?? null) ? $_POST['cibles'] : [];
    $groupes_envoyes = is_array($_POST['groupes'] ?? null) ? $_POST['groupes'] : [];

    $ids_membres = array_map('intval', array_column(autres_membres($moi_id), 'id'));
    $ids_groupes = array_map('intval', array_column(mes_groupes($moi_id), 'id'));

    return [
        'membres' => array_values(array_intersect(array_map('intval', $membres_envoyes), $ids_membres)),
        'groupes' => array_values(array_intersect(array_map('intval', $groupes_envoyes), $ids_groupes)),
    ];
}


/**
 * Enregistre les membres et groupes choisis (remplace les anciens choix).
 * Si $actif est false (l'élément n'est pas « Membres choisis »), tout est effacé.
 */
function enregistrer_cibles(string $type, int $id, array $cibles, bool $actif): void
{
    $t = tables_cibles($type);
    $bdd = bdd();

    $bdd->prepare("DELETE FROM {$t['membres']} WHERE {$t['colonne']} = :id")->execute(['id' => $id]);
    $bdd->prepare("DELETE FROM {$t['groupes']} WHERE {$t['colonne']} = :id")->execute(['id' => $id]);

    if (!$actif) {
        return;
    }

    $ajout = $bdd->prepare("INSERT INTO {$t['membres']} ({$t['colonne']}, membre_id) VALUES (:id, :valeur)");
    foreach ($cibles['membres'] as $membre_id) {
        $ajout->execute(['id' => $id, 'valeur' => $membre_id]);
    }
    $ajout = $bdd->prepare("INSERT INTO {$t['groupes']} ({$t['colonne']}, groupe_id) VALUES (:id, :valeur)");
    foreach ($cibles['groupes'] as $groupe_id) {
        $ajout->execute(['id' => $id, 'valeur' => $groupe_id]);
    }
}


/**
 * Code HTML du choix des « Membres choisis » : groupes, puis personnes.
 * Utilisé par le formulaire des missions et celui des disponibilités.
 */
function champ_cibles(int $moi_id, array $choix, ?string $erreur): string
{
    $groupes = mes_groupes($moi_id);
    $membres = autres_membres($moi_id);
    $classe  = $erreur ? 'champ champ-en-erreur' : 'champ';

    $html = '<div class="' . $classe . '">';

    // Groupes
    $html .= '<fieldset class="groupe-choix"><legend>Mes groupes</legend>';
    if ($groupes) {
        foreach ($groupes as $groupe) {
            $coche = in_array((int) $groupe['id'], $choix['groupes'], true) ? ' checked' : '';
            $html .= '<label class="choix"><input type="checkbox" name="groupes[]" value="' . (int) $groupe['id'] . '"' . $coche . '> '
                   . e($groupe['nom']) . ' <span class="texte-discret texte-petit">(' . (int) $groupe['nombre_membres'] . ')</span></label>';
        }
    } else {
        $html .= '<p class="champ-aide">Vous n\'avez pas encore de groupe.</p>';
    }
    $html .= '<p class="champ-aide"><a href="' . e(url('modules/members/groupes.php')) . '">Gérer mes groupes</a></p>';
    $html .= '</fieldset>';

    // Personnes
    $html .= '<label for="cibles">Et / ou des personnes</label>'
           . '<select id="cibles" name="cibles[]" multiple size="8">';
    foreach ($membres as $membre) {
        $selection = in_array((int) $membre['id'], $choix['membres'], true) ? ' selected' : '';
        $html .= '<option value="' . (int) $membre['id'] . '"' . $selection . '>' . e($membre['nom'] . ' ' . $membre['prenom']) . '</option>';
    }
    $html .= '</select>'
           . '<p class="champ-aide">Seulement si vous avez choisi « Membres choisis ». Maintenez ⌘ (Cmd) pour sélectionner plusieurs personnes.</p>';

    if ($erreur) {
        $html .= '<p class="champ-erreur">' . e($erreur) . '</p>';
    }
    return $html . '</div>';
}


/** Texte « Groupe A, Groupe B, Nina Keller » des membres choisis (pour l'affichage). */
function texte_cibles(string $type, int $id): string
{
    $t = tables_cibles($type);
    $requete = bdd()->prepare(
        "SELECT g.nom FROM {$t['groupes']} cg JOIN groupes g ON g.id = cg.groupe_id
         WHERE cg.{$t['colonne']} = :id ORDER BY g.nom"
    );
    $requete->execute(['id' => $id]);
    $noms = array_map(fn($nom) => 'Groupe « ' . $nom . ' »', $requete->fetchAll(PDO::FETCH_COLUMN));

    $requete = bdd()->prepare(
        "SELECT CONCAT(m.prenom, ' ', m.nom) FROM {$t['membres']} cm JOIN membres m ON m.id = cm.membre_id
         WHERE cm.{$t['colonne']} = :id ORDER BY m.nom, m.prenom"
    );
    $requete->execute(['id' => $id]);
    return implode(', ', array_merge($noms, $requete->fetchAll(PDO::FETCH_COLUMN)));
}


/** Nombre d'éléments à traiter (invitations reçues + candidatures reçues), pour le menu. */
function nombre_a_traiter(int $moi_id): int
{
    try {
        $requete = bdd()->prepare(
            "SELECT
               (SELECT COUNT(*) FROM candidatures ca JOIN missions mi ON mi.id = ca.mission_id
                WHERE ca.membre_id = :moi1 AND ca.statut = 'invitee' AND mi.statut = 'ouverte')
             + (SELECT COUNT(*) FROM candidatures ca JOIN missions mi ON mi.id = ca.mission_id
                WHERE mi.auteur_id = :moi2 AND ca.statut = 'en_attente' AND mi.statut = 'ouverte')"
        );
        $requete->execute(['moi1' => $moi_id, 'moi2' => $moi_id]);
        return (int) $requete->fetchColumn();
    } catch (PDOException $erreur) {
        return 0;   // tables pas encore créées (installation en cours)
    }
}
