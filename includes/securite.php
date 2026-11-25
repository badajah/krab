<?php
/* ==========================================================================
   KRAB — includes/securite.php
   --------------------------------------------------------------------------
   Outils de sécurité utilisés dans tout le site :
     1. démarrage d'une session sécurisée ;
     2. e() : affichage sans risque d'un texte (protection XSS) ;
     3. protection CSRF des formulaires.
   ========================================================================== */


/* --------------------------------------------------------------------------
   1. SESSION SÉCURISÉE
   La session permet de "se souvenir" d'un visiteur d'une page à l'autre
   (par exemple : savoir qu'il est connecté).
   -------------------------------------------------------------------------- */

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // déjà démarrée
    }

    // Le site est-il en HTTPS ? (pas le cas avec MAMP, oui chez un hébergeur)
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,              // le cookie disparaît à la fermeture du navigateur
        'path'     => SITE_CHEMIN . '/',
        'secure'   => $https,         // cookie envoyé uniquement en HTTPS si disponible
        'httponly' => true,           // inaccessible au JavaScript (vol de session)
        'samesite' => 'Lax',          // limite l'envoi du cookie depuis d'autres sites
    ]);

    // Refuse les identifiants de session inventés par un visiteur
    ini_set('session.use_strict_mode', '1');
    // L'identifiant de session ne passe jamais dans l'adresse de la page
    ini_set('session.use_only_cookies', '1');

    session_name('krab_session');
    session_start();
}


/* --------------------------------------------------------------------------
   2. AFFICHAGE SÉCURISÉ : e()
   TOUTE donnée affichée dans une page (nom d'un membre, texte d'une offre,
   contenu d'un formulaire...) doit passer par e().

   Exemple :   <p><?= e($membre['nom']) ?></p>

   Sans cela, un membre malveillant pourrait écrire du code dans son nom
   et le faire exécuter chez les autres (faille "XSS").
   -------------------------------------------------------------------------- */

function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* --------------------------------------------------------------------------
   3. PROTECTION CSRF
   Empêche un autre site d'envoyer un formulaire à la place d'un membre
   connecté. Chaque formulaire contient un "jeton" secret que seul notre
   site connaît ; on vérifie ce jeton à la réception.

   Dans le formulaire :
       <form method="post" action="...">
           <?= csrf_champ() ?>
           ...
       </form>

   Dans le traitement :
       if ($_SERVER['REQUEST_METHOD'] === 'POST') {
           csrf_verifier();
           ... traitement ...
       }
   -------------------------------------------------------------------------- */

/** Renvoie le jeton de la session (le crée s'il n'existe pas encore). */
function csrf_jeton(): string
{
    if (empty($_SESSION['csrf_jeton'])) {
        $_SESSION['csrf_jeton'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_jeton'];
}

/** Renvoie le champ caché à placer dans chaque formulaire. */
function csrf_champ(): string
{
    return '<input type="hidden" name="csrf_jeton" value="' . e(csrf_jeton()) . '">';
}

/** Vérifie le jeton reçu. Arrête tout si le jeton est absent ou faux. */
function csrf_verifier(): void
{
    $jeton_recu = $_POST['csrf_jeton'] ?? '';

    // hash_equals compare deux textes de façon sûre
    if (!is_string($jeton_recu) || !hash_equals(csrf_jeton(), $jeton_recu)) {
        http_response_code(403);
        exit('Action refusée : le formulaire a expiré. Rechargez la page et réessayez.');
    }
}
