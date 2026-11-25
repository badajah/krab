<?php
/* ==========================================================================
   KRAB — includes/membres.php
   --------------------------------------------------------------------------
   Fonctions communes liées aux profils des membres :
   cantons, compétences, photos et avatars.
   ========================================================================== */

// Nombre maximum de compétences par membre
const COMPETENCES_MAXIMUM = 3;

// Photos de profil : taille maximale du fichier envoyé, et taille finale
const PHOTO_POIDS_MAXIMUM = 5 * 1024 * 1024;   // 5 Mo
const PHOTO_DIMENSION     = 600;               // largeur/hauteur max en pixels

// Dossier où sont rangées les photos (protégé : voir uploads/.htaccess)
define('DOSSIER_PHOTOS', __DIR__ . '/../uploads/photos');


/** Liste des cantons suisses : code => nom. */
function cantons(): array
{
    return [
        'AG' => 'Argovie',            'AI' => 'Appenzell Rh.-Int.',
        'AR' => 'Appenzell Rh.-Ext.', 'BE' => 'Berne',
        'BL' => 'Bâle-Campagne',      'BS' => 'Bâle-Ville',
        'FR' => 'Fribourg',           'GE' => 'Genève',
        'GL' => 'Glaris',             'GR' => 'Grisons',
        'JU' => 'Jura',               'LU' => 'Lucerne',
        'NE' => 'Neuchâtel',          'NW' => 'Nidwald',
        'OW' => 'Obwald',             'SG' => 'Saint-Gall',
        'SH' => 'Schaffhouse',        'SO' => 'Soleure',
        'SZ' => 'Schwytz',            'TG' => 'Thurgovie',
        'TI' => 'Tessin',             'UR' => 'Uri',
        'VD' => 'Vaud',               'VS' => 'Valais',
        'ZG' => 'Zoug',               'ZH' => 'Zurich',
    ];
}


/** Nom complet d'un canton à partir de son code (ou '' si inconnu). */
function nom_canton(?string $code): string
{
    return cantons()[$code ?? ''] ?? '';
}


/** Toutes les compétences proposées, par ordre alphabétique. */
function toutes_les_competences(): array
{
    return bdd()->query('SELECT id, nom FROM competences ORDER BY nom')->fetchAll();
}


/** Compétences d'un membre, par ordre alphabétique. */
function competences_du_membre(int $membre_id): array
{
    $requete = bdd()->prepare(
        'SELECT c.id, c.nom
         FROM competences c
         JOIN membres_competences mc ON mc.competence_id = c.id
         WHERE mc.membre_id = :id
         ORDER BY c.nom'
    );
    $requete->execute(['id' => $membre_id]);
    return $requete->fetchAll();
}


/** Initiales d'un membre (ex. « Camille Martin » → « CM »). */
function initiales(array $membre): string
{
    return mb_strtoupper(mb_substr($membre['prenom'], 0, 1) . mb_substr($membre['nom'], 0, 1));
}


/**
 * Code HTML de l'avatar d'un membre : sa photo, ou ses initiales.
 * $grand = true pour la grande version (page profil).
 */
function avatar(array $membre, bool $grand = false): string
{
    $classes = 'avatar' . ($grand ? ' avatar-grand' : '');

    if (!empty($membre['photo'])) {
        // La photo passe par photo.php, qui vérifie que le visiteur est connecté
        $adresse = url('modules/members/photo.php?id=' . (int) $membre['id']
                       . '&v=' . rawurlencode($membre['photo']));
        return '<img class="' . $classes . '" src="' . e($adresse) . '" alt="">';
    }

    return '<span class="' . $classes . ' avatar-initiales" aria-hidden="true">'
         . e(initiales($membre)) . '</span>';
}


/**
 * Enregistre une photo envoyée par formulaire.
 *
 * Pour la sécurité et la confidentialité, l'image est REDESSINÉE :
 * cela supprime tout contenu caché et les métadonnées (lieu de la prise
 * de vue, appareil...). Elle est aussi réduite à PHOTO_DIMENSION pixels.
 *
 * Renvoie le nom du fichier créé, ou lance une exception avec un
 * message d'erreur lisible.
 */
function enregistrer_photo(array $fichier): string
{
    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('La photo n\'a pas pu être envoyée. Réessayez avec un fichier plus léger.');
    }
    if ($fichier['size'] > PHOTO_POIDS_MAXIMUM) {
        throw new RuntimeException('La photo est trop lourde (5 Mo maximum).');
    }

    // Vérifie le VRAI type du fichier (pas seulement son nom)
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Format non accepté : utilisez une image JPEG, PNG ou WebP.');
    }

    $image = @imagecreatefromstring((string) file_get_contents($fichier['tmp_name']));
    if ($image === false) {
        throw new RuntimeException('Cette image est illisible.');
    }

    // Calcul de la nouvelle taille (sans déformer l'image)
    $largeur = imagesx($image);
    $hauteur = imagesy($image);
    $echelle = min(1, PHOTO_DIMENSION / max($largeur, $hauteur));
    $nouvelle_largeur = max(1, (int) round($largeur * $echelle));
    $nouvelle_hauteur = max(1, (int) round($hauteur * $echelle));

    // Nouvelle image sur fond blanc (les zones transparentes deviennent blanches)
    $copie = imagecreatetruecolor($nouvelle_largeur, $nouvelle_hauteur);
    imagefill($copie, 0, 0, imagecolorallocate($copie, 255, 255, 255));
    imagecopyresampled($copie, $image, 0, 0, 0, 0,
                       $nouvelle_largeur, $nouvelle_hauteur, $largeur, $hauteur);

    if (!is_dir(DOSSIER_PHOTOS)) {
        mkdir(DOSSIER_PHOTOS, 0755, true);
    }

    // Nom de fichier aléatoire : impossible à deviner
    $nom = bin2hex(random_bytes(16)) . '.jpg';
    imagejpeg($copie, DOSSIER_PHOTOS . '/' . $nom, 85);

    imagedestroy($image);
    imagedestroy($copie);

    return $nom;
}


/** Supprime un fichier photo (si le nom est valide et que le fichier existe). */
function supprimer_photo(?string $nom): void
{
    // Le nom doit avoir exactement la forme créée par enregistrer_photo()
    if ($nom !== null && preg_match('/^[a-f0-9]{32}\.jpg$/', $nom)) {
        $chemin = DOSSIER_PHOTOS . '/' . $nom;
        if (is_file($chemin)) {
            unlink($chemin);
        }
    }
}
