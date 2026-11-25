-- ==========================================================================
-- KRAB — database/migrations/001_membres.sql
-- --------------------------------------------------------------------------
-- Crée la table des membres.
--
-- COMMENT L'UTILISER AVEC MAMP :
--   1. Ouvrir phpMyAdmin : http://localhost:8888/phpMyAdmin/
--   2. Cliquer sur l'onglet "SQL" en haut.
--   3. Copier-coller tout ce fichier, puis cliquer sur "Exécuter".
--
-- Les fichiers de ce dossier sont numérotés (001, 002...) : on les
-- exécute dans l'ordre, une seule fois chacun.
-- ==========================================================================

USE krab;

CREATE TABLE IF NOT EXISTS membres (
    -- Numéro unique du membre, attribué automatiquement
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,

    prenom            VARCHAR(100) NOT NULL,
    nom               VARCHAR(100) NOT NULL,

    -- L'e-mail sert d'identifiant de connexion : il doit être unique
    email             VARCHAR(255) NOT NULL,

    -- Mot de passe PROTÉGÉ par password_hash() (jamais le mot de passe en clair)
    mot_de_passe      VARCHAR(255) NOT NULL,

    date_inscription  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME    NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
