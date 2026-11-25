<?php
/* ==========================================================================
   KRAB — modules/members/photo.php
   --------------------------------------------------------------------------
   Affiche la photo de profil d'un membre, UNIQUEMENT pour les membres
   connectés. Les photos ne sont jamais accessibles directement
   (voir uploads/.htaccess).

   Utilisation : <img src="photo.php?id=12"> (la fonction avatar() s'en charge)
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

if (!est_connecte()) {
    http_response_code(403);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$requete = bdd()->prepare('SELECT photo FROM membres WHERE id = :id');
$requete->execute(['id' => $id]);
$nom = $requete->fetchColumn();

// Le nom doit avoir la forme créée par enregistrer_photo()
if (!$nom || !preg_match('/^[a-f0-9]{32}\.jpg$/', $nom) || !is_file(DOSSIER_PHOTOS . '/' . $nom)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize(DOSSIER_PHOTOS . '/' . $nom));
// "private" : le navigateur peut garder l'image, mais pas un serveur intermédiaire
header('Cache-Control: private, max-age=86400');
readfile(DOSSIER_PHOTOS . '/' . $nom);
