<?php
/* ==========================================================================
   KRAB — modules/members/inscription.php
   --------------------------------------------------------------------------
   Inscription d'un nouveau membre. Ouverte à tous.

   Fonctionnement :
     - Première visite (GET) : on affiche le formulaire vide.
     - Envoi du formulaire (POST) : on vérifie les données.
         · S'il y a des erreurs, on réaffiche le formulaire avec les erreurs.
         · Sinon, on crée le compte, on connecte le membre et on l'envoie
           vers l'accueil.
   ========================================================================== */

require __DIR__ . '/../../includes/initialisation.php';

// Un membre déjà connecté n'a rien à faire ici
exiger_visiteur();

// Longueur minimale du mot de passe
const MOT_DE_PASSE_MINIMUM = 10;

// Valeurs du formulaire (vides au départ) et liste des erreurs
$valeurs = ['prenom' => '', 'nom' => '', 'email' => ''];
$erreurs = [];


/* --------------------------------------------------------------------------
   TRAITEMENT DU FORMULAIRE
   -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verifier();

    // 1. Récupération des données (trim = enlève les espaces au début et à la fin)
    $valeurs['prenom'] = trim((string) ($_POST['prenom'] ?? ''));
    $valeurs['nom']    = trim((string) ($_POST['nom'] ?? ''));
    $valeurs['email']  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $mot_de_passe      = (string) ($_POST['mot_de_passe'] ?? '');
    $confirmation      = (string) ($_POST['confirmation'] ?? '');

    // 2. Vérifications, champ par champ
    if ($valeurs['prenom'] === '') {
        $erreurs['prenom'] = 'Indiquez votre prénom.';
    } elseif (mb_strlen($valeurs['prenom']) > 100) {
        $erreurs['prenom'] = 'Le prénom est trop long (100 caractères maximum).';
    }

    if ($valeurs['nom'] === '') {
        $erreurs['nom'] = 'Indiquez votre nom.';
    } elseif (mb_strlen($valeurs['nom']) > 100) {
        $erreurs['nom'] = 'Le nom est trop long (100 caractères maximum).';
    }

    if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($valeurs['email']) > 255) {
        $erreurs['email'] = 'Indiquez une adresse e-mail valide.';
    } else {
        // L'adresse est-elle déjà utilisée ?
        $requete = bdd()->prepare('SELECT id FROM membres WHERE email = :email');
        $requete->execute(['email' => $valeurs['email']]);
        if ($requete->fetch()) {
            $erreurs['email'] = 'Cette adresse est déjà utilisée. Vous avez peut-être déjà un compte ?';
        }
    }

    if (mb_strlen($mot_de_passe) < MOT_DE_PASSE_MINIMUM) {
        $erreurs['mot_de_passe'] = 'Le mot de passe doit contenir au moins ' . MOT_DE_PASSE_MINIMUM . ' caractères.';
    } elseif ($mot_de_passe !== $confirmation) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    }

    // 3. Aucune erreur : création du compte
    if (empty($erreurs)) {
        $requete = bdd()->prepare(
            'INSERT INTO membres (prenom, nom, email, mot_de_passe)
             VALUES (:prenom, :nom, :email, :mot_de_passe)'
        );
        $requete->execute([
            'prenom'       => $valeurs['prenom'],
            'nom'          => $valeurs['nom'],
            'email'        => $valeurs['email'],
            // Le mot de passe est protégé AVANT d'être enregistré
            'mot_de_passe' => password_hash($mot_de_passe, PASSWORD_DEFAULT),
        ]);

        connecter_membre((int) bdd()->lastInsertId());
        message_flash('succes', 'Bienvenue sur KRAB, ' . $valeurs['prenom'] . ' ! Votre compte est créé.');
        rediriger('');
    }
}


/* --------------------------------------------------------------------------
   AFFICHAGE
   -------------------------------------------------------------------------- */

$titre_page  = 'Inscription';
$page_active = 'inscription';
require __DIR__ . '/../../includes/entete.php';

/**
 * Petit outil pour cette page : renvoie la classe "champ-en-erreur"
 * si le champ a une erreur.
 */
function classe_champ(string $nom, array $erreurs): string
{
    return isset($erreurs[$nom]) ? 'champ champ-en-erreur' : 'champ';
}
?>

<div class="entete-page">
    <h1>Inscription</h1>
    <p class="texte-discret">Rejoignez la communauté. Tous les membres ont les mêmes droits.</p>
</div>

<?php if (!empty($erreurs)): ?>
    <div class="message message-erreur" role="alert">
        Le formulaire contient des erreurs. Corrigez les champs indiqués ci-dessous.
    </div>
<?php endif; ?>

<form class="formulaire" method="post" action="" novalidate>
    <?= csrf_champ() ?>

    <div class="champs-cote-a-cote">
        <div class="<?= classe_champ('prenom', $erreurs) ?>">
            <label for="prenom">Prénom <span class="obligatoire">*</span></label>
            <input type="text" id="prenom" name="prenom" required maxlength="100"
                   autocomplete="given-name" value="<?= e($valeurs['prenom']) ?>">
            <?php if (isset($erreurs['prenom'])): ?>
                <p class="champ-erreur"><?= e($erreurs['prenom']) ?></p>
            <?php endif; ?>
        </div>

        <div class="<?= classe_champ('nom', $erreurs) ?>">
            <label for="nom">Nom <span class="obligatoire">*</span></label>
            <input type="text" id="nom" name="nom" required maxlength="100"
                   autocomplete="family-name" value="<?= e($valeurs['nom']) ?>">
            <?php if (isset($erreurs['nom'])): ?>
                <p class="champ-erreur"><?= e($erreurs['nom']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="<?= classe_champ('email', $erreurs) ?>">
        <label for="email">Adresse e-mail <span class="obligatoire">*</span></label>
        <input type="email" id="email" name="email" required maxlength="255"
               autocomplete="email" value="<?= e($valeurs['email']) ?>">
        <p class="champ-aide">Elle sert à vous connecter. Elle ne sera pas affichée publiquement.</p>
        <?php if (isset($erreurs['email'])): ?>
            <p class="champ-erreur"><?= e($erreurs['email']) ?></p>
        <?php endif; ?>
    </div>

    <div class="<?= classe_champ('mot_de_passe', $erreurs) ?>">
        <label for="mot_de_passe">Mot de passe <span class="obligatoire">*</span></label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required
               minlength="<?= MOT_DE_PASSE_MINIMUM ?>" autocomplete="new-password">
        <p class="champ-aide">Au moins <?= MOT_DE_PASSE_MINIMUM ?> caractères.</p>
        <?php if (isset($erreurs['mot_de_passe'])): ?>
            <p class="champ-erreur"><?= e($erreurs['mot_de_passe']) ?></p>
        <?php endif; ?>
    </div>

    <div class="<?= classe_champ('confirmation', $erreurs) ?>">
        <label for="confirmation">Confirmez le mot de passe <span class="obligatoire">*</span></label>
        <input type="password" id="confirmation" name="confirmation" required
               autocomplete="new-password">
        <?php if (isset($erreurs['confirmation'])): ?>
            <p class="champ-erreur"><?= e($erreurs['confirmation']) ?></p>
        <?php endif; ?>
    </div>

    <div class="actions-formulaire">
        <button class="bouton" type="submit">Créer mon compte</button>
        <a href="<?= e(url('modules/members/connexion.php')) ?>">J'ai déjà un compte</a>
    </div>
</form>

<?php
require __DIR__ . '/../../includes/pied-page.php';
