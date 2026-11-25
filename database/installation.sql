-- ==========================================================================
-- KRAB — database/installation.sql
-- --------------------------------------------------------------------------
-- Crée la base de données du site.
--
-- COMMENT L'UTILISER AVEC MAMP :
--   1. Ouvrir MAMP et cliquer sur "Start" (démarrer les serveurs).
--   2. Ouvrir phpMyAdmin : http://localhost:8888/phpMyAdmin/
--   3. Cliquer sur l'onglet "SQL" en haut.
--   4. Copier-coller tout ce fichier, puis cliquer sur "Exécuter".
--
-- Les tables (membres, missions, agenda...) seront ajoutées plus tard,
-- module par module, dans des fichiers séparés.
-- ==========================================================================

-- utf8mb4 : encodage complet (tous les accents, toutes les langues, emojis)
CREATE DATABASE IF NOT EXISTS krab
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
