<?php
/* ==========================================================================
   KRAB — modules/applications/action.php
   --------------------------------------------------------------------------
   Traite les actions sur les candidatures et les missions.
   Reçoit uniquement des formulaires (POST) protégés par CSRF.

   Actions :
     candidater       (id = mission)      → un membre se propose
     retirer          (id = candidature)  → le membre retire sa candidature
     accepter         (id = candidature)  → l'auteur accepte : CONFIRMÉ
     refuser          (id = candidature)  → l'auteur refuse
     inviter          (id = mission, membre_id) → l'auteur invite un membre
     accepter_invitation / refuser_invitation (id = candidature) → le membre invité répond
     annuler_invitation (id = candidature) → l'auteur annule une invitation
     annuler_mission  (id = mission)      → l'auteur annule sa mission

   Pour chaque action, on vérifie côté serveur que le membre connecté
   a bien le droit de la faire.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';
require __DIR__ . '/../listings/fonctions.php';

exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rediriger('modules/agenda/index.php');
}
csrf_verifier();

$moi_id = (int) membre_connecte()['id'];
$action = (string) ($_POST['action'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);


/** Arrête l'action avec un message d'erreur et retourne à la mission. */
function refuser_action(string $texte, int $mission_id): void
{
    message_flash('erreur', $texte);
    rediriger($mission_id > 0 ? 'modules/listings/voir.php?id=' . $mission_id : 'modules/agenda/index.php');
}

/** Lit une candidature avec sa mission. */
function lire_candidature(int $id): ?array
{
    $requete = bdd()->prepare('SELECT * FROM candidatures WHERE id = :id');
    $requete->execute(['id' => $id]);
    return $requete->fetch() ?: null;
}


switch ($action) {

    /* ---------------------------------------------------------------------
       Un membre se propose pour une mission
       --------------------------------------------------------------------- */
    case 'candidater':
        $mission = lire_mission($id);
        if (!$mission || !peut_voir_mission($mission, $moi_id)) {
            refuser_action('Cette mission n\'est pas accessible.', 0);
        }
        $raison = raison_refus_candidature($mission, $moi_id);
        if ($raison !== '') {
            refuser_action($raison, $id);
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        if (mb_strlen($message) > 2000) {
            refuser_action('Le message est trop long (2000 caractères maximum).', $id);
        }

        $existante = candidature_de($id, $moi_id);
        if ($existante && $existante['statut'] !== 'retiree') {
            refuser_action('Vous vous êtes déjà proposé pour cette mission.', $id);
        }

        if ($existante) {
            // Candidature retirée auparavant : on la réactive
            $requete = bdd()->prepare(
                "UPDATE candidatures SET statut = 'en_attente', message = :message,
                        date_creation = NOW(), date_reponse = NULL
                 WHERE id = :id"
            );
            $requete->execute(['message' => $message !== '' ? $message : null, 'id' => $existante['id']]);
        } else {
            $requete = bdd()->prepare(
                'INSERT INTO candidatures (mission_id, membre_id, message) VALUES (:mission, :membre, :message)'
            );
            $requete->execute(['mission' => $id, 'membre' => $moi_id, 'message' => $message !== '' ? $message : null]);
        }

        message_flash('succes', 'Votre proposition est envoyée à ' . $mission['auteur_prenom'] . '.');
        rediriger('modules/listings/voir.php?id=' . $id);


    /* ---------------------------------------------------------------------
       Le membre retire sa candidature (même si elle était acceptée :
       la place se libère)
       --------------------------------------------------------------------- */
    case 'retirer':
        $candidature = lire_candidature($id);
        if (!$candidature || (int) $candidature['membre_id'] !== $moi_id) {
            refuser_action('Action impossible.', 0);
        }
        if (!in_array($candidature['statut'], ['en_attente', 'acceptee'], true)) {
            refuser_action('Cette candidature ne peut plus être retirée.', (int) $candidature['mission_id']);
        }
        $requete = bdd()->prepare("UPDATE candidatures SET statut = 'retiree', date_reponse = NOW() WHERE id = :id");
        $requete->execute(['id' => $id]);

        message_flash('info', 'Votre candidature est retirée.');
        rediriger('modules/listings/voir.php?id=' . (int) $candidature['mission_id']);


    /* ---------------------------------------------------------------------
       L'auteur accepte ou refuse une candidature
       --------------------------------------------------------------------- */
    case 'accepter':
    case 'refuser':
        $candidature = lire_candidature($id);
        $mission = $candidature ? lire_mission((int) $candidature['mission_id']) : null;

        // Seul l'auteur de la mission peut répondre
        if (!$mission || (int) $mission['auteur_id'] !== $moi_id) {
            refuser_action('Action impossible.', 0);
        }
        if ($candidature['statut'] !== 'en_attente' || $mission['statut'] !== 'ouverte') {
            refuser_action('Cette candidature a déjà reçu une réponse.', (int) $mission['id']);
        }
        if ($action === 'accepter' && mission_complete($mission)) {
            refuser_action('Toutes les places sont déjà prises.', (int) $mission['id']);
        }

        $nouveau_statut = $action === 'accepter' ? 'acceptee' : 'refusee';
        $requete = bdd()->prepare('UPDATE candidatures SET statut = :statut, date_reponse = NOW() WHERE id = :id');
        $requete->execute(['statut' => $nouveau_statut, 'id' => $id]);

        message_flash('succes', $action === 'accepter'
            ? 'Candidature acceptée : la mission est confirmée pour cette personne.'
            : 'Candidature refusée.');
        rediriger('modules/listings/voir.php?id=' . (int) $mission['id']);


    /* ---------------------------------------------------------------------
       L'auteur invite un membre sur sa mission
       (id = mission, membre_id = membre invité)
       --------------------------------------------------------------------- */
    case 'inviter':
        $mission   = lire_mission($id);
        $membre_id = (int) ($_POST['membre_id'] ?? 0);

        // Seul l'auteur peut inviter
        if (!$mission || (int) $mission['auteur_id'] !== $moi_id) {
            refuser_action('Action impossible.', 0);
        }
        if ($mission['statut'] !== 'ouverte' || ($mission['date_fin'] ?: $mission['date_debut']) < date('Y-m-d')) {
            refuser_action('On ne peut plus inviter sur cette mission.', $id);
        }
        if (mission_complete($mission)) {
            refuser_action('Toutes les places sont déjà prises.', $id);
        }

        $requete = bdd()->prepare('SELECT prenom FROM membres WHERE id = :id AND id <> :moi');
        $requete->execute(['id' => $membre_id, 'moi' => $moi_id]);
        $prenom_invite = $requete->fetchColumn();
        if ($prenom_invite === false) {
            refuser_action('Choisissez un membre à inviter.', $id);
        }

        $existante = candidature_de($id, $membre_id);
        if ($existante && $existante['statut'] === 'en_attente') {
            refuser_action($prenom_invite . ' s\'est déjà proposé : vous pouvez accepter sa candidature.', $id);
        }
        if ($existante && $existante['statut'] !== 'retiree') {
            refuser_action($prenom_invite . ' a déjà reçu une réponse ou une invitation pour cette mission.', $id);
        }

        if ($existante) {
            $requete = bdd()->prepare(
                "UPDATE candidatures SET statut = 'invitee', origine = 'invitation', message = NULL,
                        date_creation = NOW(), date_reponse = NULL
                 WHERE id = :id"
            );
            $requete->execute(['id' => $existante['id']]);
        } else {
            $requete = bdd()->prepare(
                "INSERT INTO candidatures (mission_id, membre_id, origine, statut)
                 VALUES (:mission, :membre, 'invitation', 'invitee')"
            );
            $requete->execute(['mission' => $id, 'membre' => $membre_id]);
        }

        message_flash('succes', 'Invitation envoyée à ' . $prenom_invite . '.');
        rediriger('modules/listings/voir.php?id=' . $id);


    /* ---------------------------------------------------------------------
       Le membre invité accepte ou refuse l'invitation
       (id = candidature)
       --------------------------------------------------------------------- */
    case 'accepter_invitation':
    case 'refuser_invitation':
        $candidature = lire_candidature($id);
        if (!$candidature || (int) $candidature['membre_id'] !== $moi_id || $candidature['statut'] !== 'invitee') {
            refuser_action('Cette invitation n\'est plus valable.', 0);
        }
        $mission = lire_mission((int) $candidature['mission_id']);
        if ($mission['statut'] !== 'ouverte') {
            refuser_action('Cette mission a été annulée.', (int) $mission['id']);
        }
        if ($action === 'accepter_invitation' && mission_complete($mission)) {
            refuser_action('Désolé, toutes les places ont été prises entre-temps.', (int) $mission['id']);
        }

        $nouveau_statut = $action === 'accepter_invitation' ? 'acceptee' : 'refusee';
        $requete = bdd()->prepare('UPDATE candidatures SET statut = :statut, date_reponse = NOW() WHERE id = :id');
        $requete->execute(['statut' => $nouveau_statut, 'id' => $id]);

        message_flash('succes', $action === 'accepter_invitation'
            ? 'C\'est confirmé : la mission est ajoutée à votre agenda.'
            : 'Vous avez refusé l\'invitation.');
        rediriger('modules/listings/voir.php?id=' . (int) $mission['id']);


    /* ---------------------------------------------------------------------
       L'auteur annule une invitation qui n'a pas encore reçu de réponse
       (id = candidature)
       --------------------------------------------------------------------- */
    case 'annuler_invitation':
        $candidature = lire_candidature($id);
        $mission = $candidature ? lire_mission((int) $candidature['mission_id']) : null;
        if (!$mission || (int) $mission['auteur_id'] !== $moi_id || $candidature['statut'] !== 'invitee') {
            refuser_action('Action impossible.', 0);
        }
        $requete = bdd()->prepare("UPDATE candidatures SET statut = 'retiree', date_reponse = NOW() WHERE id = :id");
        $requete->execute(['id' => $id]);

        message_flash('info', 'Invitation annulée.');
        rediriger('modules/listings/voir.php?id=' . (int) $mission['id']);


    /* ---------------------------------------------------------------------
       L'auteur annule sa mission
       --------------------------------------------------------------------- */
    case 'annuler_mission':
        $mission = lire_mission($id);
        if (!$mission || (int) $mission['auteur_id'] !== $moi_id) {
            refuser_action('Action impossible.', 0);
        }
        $requete = bdd()->prepare("UPDATE missions SET statut = 'annulee' WHERE id = :id AND auteur_id = :auteur");
        $requete->execute(['id' => $id, 'auteur' => $moi_id]);

        message_flash('info', 'La mission est annulée.');
        rediriger('modules/listings/voir.php?id=' . $id);


    default:
        refuser_action('Action inconnue.', 0);
}
