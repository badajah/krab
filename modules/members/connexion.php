<?php
/* ==========================================================================
   KRAB — modules/members/connexion.php
   --------------------------------------------------------------------------
   Connexion d'un membre avec son e-mail et son mot de passe.

   Sécurité :
     - le message d'erreur est le même que l'e-mail soit inconnu ou le mot
       de passe faux (on ne révèle pas quelles adresses sont inscrites) ;
     - après 5 essais ratés, il faut attendre quelques minutes
       (protection contre ceux qui essaient des mots de passe au hasard).
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

exiger_visiteur();

const ESSAIS_MAXIMUM = 5;         // nombre d'essais ratés autorisés
const ATTENTE_MINUTES = 5;        // durée du blocage ensuite

$email  = '';
$erreur = '';

// Compteur d'essais ratés, gardé dans la session
$essais = $_SESSION['connexion_essais'] ?? ['nombre' => 0, 'depuis' => time()];

// Le blocage est terminé : on remet le compteur à zéro
if (time() - $essais['depuis'] > ATTENTE_MINUTES * 60) {
    $essais = ['nombre' => 0, 'depuis' => time()];
}

$bloque = $essais['nombre'] >= ESSAIS_MAXIMUM;


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
   -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloque) {

    csrf_verifier();

    $email        = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $mot_de_passe = (string) ($_POST['mot_de_passe'] ?? '');

    $requete = bdd()->prepare('SELECT id, prenom, mot_de_passe FROM membres WHERE email = :email');
    $requete->execute(['email' => $email]);
    $membre = $requete->fetch();

    // password_verify compare le mot de passe tapé avec la version protégée
    if ($membre && password_verify($mot_de_passe, $membre['mot_de_passe'])) {

        // Si PHP propose une protection plus récente, on met à jour
        if (password_needs_rehash($membre['mot_de_passe'], PASSWORD_DEFAULT)) {
            $maj = bdd()->prepare('UPDATE membres SET mot_de_passe = :mdp WHERE id = :id');
            $maj->execute([
                'mdp' => password_hash($mot_de_passe, PASSWORD_DEFAULT),
                'id'  => $membre['id'],
            ]);
        }

        unset($_SESSION['connexion_essais']);
        connecter_membre((int) $membre['id']);
        message_flash('succes', 'Bonjour ' . $membre['prenom'] . ', vous êtes connecté.');
        rediriger('');
    }

    // Échec : on compte l'essai
    $essais['nombre']++;
    $essais['depuis'] = time();
    $bloque = $essais['nombre'] >= ESSAIS_MAXIMUM;

    $erreur = 'Adresse e-mail ou mot de passe incorrect.';
}

$_SESSION['connexion_essais'] = $essais;


/* --------------------------------------------------------------------------
   AFFICHAGE
   -------------------------------------------------------------------------- */

$titre_page  = 'Connexion';
$page_active = 'connexion';
require __DIR__ . '/../../includes/entete.php';
?>

<div class="entete-page">
    <h1>Connexion</h1>
</div>

<?php if ($bloque): ?>
    <div class="message message-alerte" role="alert">
        Trop d'essais. Patientez <?= ATTENTE_MINUTES ?> minutes avant de réessayer.
    </div>
<?php elseif ($erreur !== ''): ?>
    <div class="message message-erreur" role="alert">
        <?= e($erreur) ?>
    </div>
<?php endif; ?>

<form class="formulaire" method="post" action="">
    <?= csrf_champ() ?>

    <div class="champ">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" required
               autocomplete="email" value="<?= e($email) ?>">
    </div>

    <div class="champ">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required
               autocomplete="current-password">
    </div>

    <div class="actions-formulaire">
        <button class="bouton" type="submit"<?= $bloque ? ' disabled' : '' ?>>Se connecter</button>
        <a href="<?= e(url('modules/members/inscription.php')) ?>">Pas encore de compte ? S'inscrire</a>
    </div>
</form>

<?php
require __DIR__ . '/../../includes/pied-page.php';
