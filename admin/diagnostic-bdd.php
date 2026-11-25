<?php
/* ==========================================================================
   KRAB — admin/diagnostic-bdd.php
   --------------------------------------------------------------------------
   Page de dépannage : essaie plusieurs façons de se connecter à MySQL
   et affiche celles qui fonctionnent. Sert à trouver les bons réglages
   pour includes/config.php.

   Adresse avec MAMP :  http://localhost:8888/krab/admin/diagnostic-bdd.php

   Accessible uniquement depuis l'ordinateur lui-même.
   Aucun mot de passe n'est affiché.
   ========================================================================== */

require __DIR__ . '/../includes/initialisation.php';

$adresse_visiteur = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($adresse_visiteur, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Accès refusé.');
}

/* --------------------------------------------------------------------------
   1. Réglages actuellement lus dans config.php et dans PHP
   -------------------------------------------------------------------------- */

$reglages = [
    'BDD_HOTE (config.php)'           => BDD_HOTE,
    'BDD_PORT (config.php)'           => (string) BDD_PORT,
    'BDD_SOCKET (config.php)'         => defined('BDD_SOCKET') ? BDD_SOCKET : '(non défini : ancien fichier config.php)',
    'Socket par défaut de PDO (PHP)'  => ini_get('pdo_mysql.default_socket') ?: '(vide)',
    'Socket par défaut de mysqli (PHP)' => ini_get('mysqli.default_socket') ?: '(vide)',
];

/* --------------------------------------------------------------------------
   2. Emplacements possibles du socket MySQL
   -------------------------------------------------------------------------- */

$sockets_possibles = array_unique(array_filter([
    '/Applications/MAMP/tmp/mysql/mysql.sock',
    '/tmp/mysql.sock',
    ini_get('pdo_mysql.default_socket'),
    ini_get('mysqli.default_socket'),
]));

/* --------------------------------------------------------------------------
   3. Méthodes de connexion à essayer (sans choisir de base, pour tester
      uniquement l'accès à MySQL)
   -------------------------------------------------------------------------- */

$essais = [];
foreach ($sockets_possibles as $socket) {
    $essais['Socket ' . $socket . (file_exists($socket) ? '' : ' (fichier absent)')]
        = 'mysql:unix_socket=' . $socket;
}
$essais['localhost (socket par défaut de PHP)'] = 'mysql:host=localhost';
$essais['localhost, port 8889']                 = 'mysql:host=localhost;port=8889';
$essais['127.0.0.1, port 8889']                 = 'mysql:host=127.0.0.1;port=8889';
$essais['127.0.0.1, port 3306']                 = 'mysql:host=127.0.0.1;port=3306';

$resultats = [];
foreach ($essais as $nom => $dsn) {
    try {
        $connexion = new PDO($dsn, BDD_UTILISATEUR, BDD_MOT_DE_PASSE, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        // La base "krab" existe-t-elle sur ce serveur ?
        $requete = $connexion->prepare('SHOW DATABASES LIKE :nom');
        $requete->execute(['nom' => BDD_NOM]);
        $base_trouvee = $requete->fetch() !== false;

        $resultats[] = [
            'nom'    => $nom,
            'reussi' => true,
            'detail' => 'Connexion réussie. Base « ' . BDD_NOM . ' » '
                      . ($base_trouvee ? 'trouvée.' : 'ABSENTE sur ce serveur.'),
        ];
    } catch (PDOException $erreur) {
        $resultats[] = [
            'nom'    => $nom,
            'reussi' => false,
            'detail' => $erreur->getMessage(),
        ];
    }
}

$titre_page  = 'Diagnostic de la base de données';
$page_active = '';
require __DIR__ . '/../includes/entete.php';
?>

<div class="entete-page">
    <h1>Diagnostic de la base de données</h1>
</div>

<section class="section">
    <h2>Réglages lus</h2>
    <dl class="details">
        <?php foreach ($reglages as $nom => $valeur): ?>
            <dt><?= e($nom) ?></dt>
            <dd><?= e($valeur) ?></dd>
        <?php endforeach; ?>
    </dl>
</section>

<section class="section">
    <h2>Essais de connexion</h2>
    <?php foreach ($resultats as $resultat): ?>
        <div class="message <?= $resultat['reussi'] ? 'message-succes' : 'message-erreur' ?>">
            <strong><?= $resultat['reussi'] ? '✓' : '✗' ?> <?= e($resultat['nom']) ?></strong>
            — <?= e($resultat['detail']) ?>
        </div>
    <?php endforeach; ?>
</section>

<?php
require __DIR__ . '/../includes/pied-page.php';
