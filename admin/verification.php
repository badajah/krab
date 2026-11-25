<?php
/* ==========================================================================
   KRAB — admin/verification.php
   --------------------------------------------------------------------------
   Page de contrôle de l'installation : vérifie que PHP et la base de
   données fonctionnent. Adresse avec MAMP :
       http://localhost:8888/krab/admin/verification.php

   Sécurité : cette page n'est accessible que depuis l'ordinateur lui-même
   (MAMP). Sur un vrai serveur, elle est refusée.
   ========================================================================== */

require __DIR__ . '/../includes/initialisation.php';

// Accès réservé à l'ordinateur local
$adresse_visiteur = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($adresse_visiteur, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Accès refusé.');
}

/* --------------------------------------------------------------------------
   Liste des contrôles. Chaque contrôle donne :
   - un nom ;
   - true (réussi) ou false (échoué) ;
   - une explication.
   -------------------------------------------------------------------------- */

$controles = [];

// 1. Version de PHP (le projet demande PHP 8.2 ou plus)
$controles[] = [
    'nom'     => 'Version de PHP',
    'reussi'  => version_compare(PHP_VERSION, '8.2.0', '>='),
    'detail'  => 'Version installée : ' . PHP_VERSION . ' (8.2 ou plus requise).',
];

// 2. Extension PDO MySQL (nécessaire pour parler à la base)
$controles[] = [
    'nom'     => 'Extension PDO MySQL',
    'reussi'  => extension_loaded('pdo_mysql'),
    'detail'  => extension_loaded('pdo_mysql') ? 'Disponible.' : 'Absente : vérifiez la configuration PHP de MAMP.',
];

// 3. Connexion à la base de données
try {
    bdd()->query('SELECT 1');
    $controles[] = [
        'nom'    => 'Base de données',
        'reussi' => true,
        'detail' => 'Connexion réussie à la base « ' . BDD_NOM . ' ».',
    ];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Base de données',
        'reussi' => false,
        'detail' => 'Connexion impossible : ' . $erreur->getMessage()
                  . ' — Vérifiez que MAMP est démarré, que la base « ' . BDD_NOM
                  . ' » existe (database/installation.sql) et les réglages de includes/config.php.',
    ];
}

// 4. Table des membres (créée par database/migrations/001_membres.sql)
try {
    bdd()->query('SELECT 1 FROM membres LIMIT 1');
    $controles[] = [
        'nom'    => 'Table des membres',
        'reussi' => true,
        'detail' => 'La table « membres » existe.',
    ];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Table des membres',
        'reussi' => false,
        'detail' => 'Table absente : exécutez database/migrations/001_membres.sql dans phpMyAdmin.',
    ];
}

// 5. Profils (créés par database/migrations/002_profils.sql)
try {
    bdd()->query('SELECT photo FROM membres LIMIT 1');
    bdd()->query('SELECT 1 FROM membres_competences LIMIT 1');
    $nombre = (int) bdd()->query('SELECT COUNT(*) FROM competences')->fetchColumn();
    $controles[] = [
        'nom'    => 'Profils et compétences',
        'reussi' => true,
        'detail' => $nombre . ' compétences disponibles.',
    ];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Profils et compétences',
        'reussi' => false,
        'detail' => 'Tables absentes : exécutez database/migrations/002_profils.sql dans phpMyAdmin.',
    ];
}

// 6. Missions (créées par database/migrations/003_missions.sql)
try {
    bdd()->query('SELECT 1 FROM missions LIMIT 1');
    bdd()->query('SELECT 1 FROM missions_cibles LIMIT 1');
    bdd()->query('SELECT 1 FROM candidatures LIMIT 1');
    $controles[] = ['nom' => 'Missions et candidatures', 'reussi' => true, 'detail' => 'Les tables existent.'];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Missions et candidatures',
        'reussi' => false,
        'detail' => 'Tables absentes : exécutez database/migrations/003_missions.sql dans phpMyAdmin.',
    ];
}

// 7. Agenda et invitations (créés par database/migrations/004_agenda.sql)
try {
    bdd()->query('SELECT 1 FROM disponibilites LIMIT 1');
    bdd()->query('SELECT 1 FROM disponibilites_cibles LIMIT 1');
    bdd()->query('SELECT origine FROM candidatures LIMIT 1');
    bdd()->query('SELECT heure_debut FROM missions LIMIT 1');
    $controles[] = ['nom' => 'Agenda et invitations', 'reussi' => true, 'detail' => 'Les tables existent.'];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Agenda et invitations',
        'reussi' => false,
        'detail' => 'Tables absentes : exécutez database/migrations/004_agenda.sql dans phpMyAdmin.',
    ];
}

// 8. Groupes (créés par database/migrations/005_groupes.sql)
try {
    bdd()->query('SELECT 1 FROM groupes LIMIT 1');
    bdd()->query('SELECT 1 FROM groupes_membres LIMIT 1');
    bdd()->query('SELECT 1 FROM missions_groupes LIMIT 1');
    bdd()->query('SELECT 1 FROM disponibilites_groupes LIMIT 1');
    $controles[] = ['nom' => 'Groupes', 'reussi' => true, 'detail' => 'Les tables existent.'];
} catch (PDOException $erreur) {
    $controles[] = [
        'nom'    => 'Groupes',
        'reussi' => false,
        'detail' => 'Tables absentes : exécutez database/migrations/005_groupes.sql dans phpMyAdmin.',
    ];
}

// 9. Traitement des photos (extension GD de PHP)
$controles[] = [
    'nom'    => 'Traitement des photos',
    'reussi' => function_exists('imagecreatefromstring') && function_exists('imagejpeg'),
    'detail' => function_exists('imagejpeg') ? 'Extension GD disponible.' : 'Extension GD absente : activez-la dans MAMP.',
];

// 10. Sessions
$controles[] = [
    'nom'    => 'Sessions',
    'reussi' => session_status() === PHP_SESSION_ACTIVE,
    'detail' => session_status() === PHP_SESSION_ACTIVE ? 'Session démarrée.' : 'La session n\'a pas pu démarrer.',
];

$titre_page  = 'Vérification de l\'installation';
$page_active = '';
require __DIR__ . '/../includes/entete.php';
?>

<div class="entete-page">
    <h1>Vérification de l'installation</h1>
</div>

<section class="section">
    <?php foreach ($controles as $controle): ?>
        <div class="message <?= $controle['reussi'] ? 'message-succes' : 'message-erreur' ?>">
            <strong><?= $controle['reussi'] ? '✓' : '✗' ?> <?= e($controle['nom']) ?></strong>
            — <?= e($controle['detail']) ?>
        </div>
    <?php endforeach; ?>
</section>

<?php
require __DIR__ . '/../includes/pied-page.php';
