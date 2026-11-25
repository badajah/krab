<?php
/* ==========================================================================
   KRAB — includes/base-de-donnees.php
   --------------------------------------------------------------------------
   Connexion à la base de données MySQL avec PDO.

   UTILISATION dans une page :

       $bdd = bdd();
       $requete = $bdd->prepare('SELECT * FROM membres WHERE id = :id');
       $requete->execute(['id' => $id]);
       $membre = $requete->fetch();

   RÈGLE DE SÉCURITÉ : toujours utiliser prepare() + execute() avec des
   marqueurs (:id, :email...). Ne JAMAIS coller une variable directement
   dans le texte d'une requête SQL (risque d'injection SQL).
   ========================================================================== */


/**
 * Renvoie la connexion à la base de données.
 *
 * La connexion n'est ouverte qu'une seule fois par page :
 * les appels suivants réutilisent la même (mot-clé "static").
 */
function bdd(): PDO
{
    static $connexion = null;

    if ($connexion === null) {
        // Le "DSN" décrit où se trouve la base et comment s'y connecter.
        // utf8mb4 = encodage complet (accents, emojis...).
        // Si le socket de MAMP existe, on passe par lui (plus fiable en local) ;
        // sinon, on se connecte par l'adresse et le port.
        if (BDD_SOCKET !== '' && file_exists(BDD_SOCKET)) {
            $acces = 'unix_socket=' . BDD_SOCKET;
        } else {
            $acces = 'host=' . BDD_HOTE . ';port=' . BDD_PORT;
        }

        $dsn = 'mysql:' . $acces
             . ';dbname=' . BDD_NOM
             . ';charset=utf8mb4';

        $options = [
            // En cas d'erreur SQL, PDO lance une exception (erreur visible)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Les résultats sont des tableaux avec le nom des colonnes
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Vraies requêtes préparées côté MySQL (plus sûr)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $connexion = new PDO($dsn, BDD_UTILISATEUR, BDD_MOT_DE_PASSE, $options);
    }

    return $connexion;
}
