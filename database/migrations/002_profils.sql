-- ==========================================================================
-- KRAB — database/migrations/002_profils.sql
-- --------------------------------------------------------------------------
-- Ajoute les profils des membres :
--   - présentation, localité, canton et photo dans la table "membres" ;
--   - la liste des compétences (définie par l'administration) ;
--   - les compétences choisies par chaque membre (3 au maximum,
--     limite vérifiée par le PHP).
--
-- À exécuter UNE fois dans phpMyAdmin (onglet SQL), après 001_membres.sql.
-- ==========================================================================

USE krab;

-- --------------------------------------------------------------------------
-- 1. Nouvelles informations du profil
-- --------------------------------------------------------------------------
ALTER TABLE membres
    ADD COLUMN presentation TEXT         NULL AFTER email,
    ADD COLUMN localite     VARCHAR(100) NULL AFTER presentation,
    ADD COLUMN canton       CHAR(2)      NULL AFTER localite,      -- ex. 'GE', 'VD'
    ADD COLUMN photo        VARCHAR(100) NULL AFTER canton;       -- nom du fichier

-- --------------------------------------------------------------------------
-- 2. Liste des compétences proposées
--    Pour en ajouter une plus tard :
--      INSERT INTO competences (nom) VALUES ('Nouvelle compétence');
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competences (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom  VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY nom_unique (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liste de départ : à adapter librement
INSERT IGNORE INTO competences (nom) VALUES
    ('Administration'),
    ('Architecture'),
    ('Artisanat'),
    ('Bâtiment et rénovation'),
    ('Communication'),
    ('Comptabilité'),
    ('Conseil juridique'),
    ('Couture et textile'),
    ('Cuisine et traiteur'),
    ('Développement web'),
    ('Électricité'),
    ('Enseignement et formation'),
    ('Événementiel'),
    ('Garde d''enfants'),
    ('Graphisme'),
    ('Illustration'),
    ('Informatique et dépannage'),
    ('Jardinage et paysagisme'),
    ('Menuiserie'),
    ('Mise en page'),
    ('Montage vidéo'),
    ('Nettoyage'),
    ('Peinture'),
    ('Photographie'),
    ('Plomberie'),
    ('Rédaction'),
    ('Santé et soins'),
    ('Son et musique'),
    ('Traduction'),
    ('Transport et déménagement');

-- --------------------------------------------------------------------------
-- 3. Compétences choisies par chaque membre
--    Une ligne = « tel membre a telle compétence ».
--    ON DELETE CASCADE : si un membre ou une compétence est supprimé,
--    les lignes correspondantes disparaissent aussi.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membres_competences (
    membre_id     INT UNSIGNED NOT NULL,
    competence_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (membre_id, competence_id),
    CONSTRAINT fk_mc_membre     FOREIGN KEY (membre_id)     REFERENCES membres (id)     ON DELETE CASCADE,
    CONSTRAINT fk_mc_competence FOREIGN KEY (competence_id) REFERENCES competences (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
