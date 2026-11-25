-- ==========================================================================
-- KRAB — database/migrations/003_missions.sql
-- --------------------------------------------------------------------------
-- Ajoute les missions (offres de travail) et les candidatures.
--
-- À exécuter UNE fois dans phpMyAdmin (onglet SQL), après 002_profils.sql.
-- ==========================================================================

USE krab;

-- --------------------------------------------------------------------------
-- 1. Missions
--    visibilite :
--      'publique' → visible par tous les membres
--      'ciblee'   → visible seulement par les membres choisis par l'auteur
--      'privee'   → visible par personne d'autre que l'auteur (brouillon)
--    statut :
--      'ouverte'  → en cours
--      'annulee'  → annulée par l'auteur
--    (une mission « complète » = toutes les places sont prises ;
--     c'est calculé à partir des candidatures acceptées)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS missions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    auteur_id      INT UNSIGNED NOT NULL,
    titre          VARCHAR(150) NOT NULL,
    description    TEXT         NOT NULL,
    competence_id  INT UNSIGNED NULL,               -- compétence recherchée (facultative)
    localite       VARCHAR(100) NULL,
    canton         CHAR(2)      NULL,
    date_debut     DATE         NOT NULL,
    date_fin       DATE         NULL,               -- vide = une seule journée
    nombre_places  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    visibilite     ENUM('publique', 'ciblee', 'privee') NOT NULL DEFAULT 'publique',
    statut         ENUM('ouverte', 'annulee') NOT NULL DEFAULT 'ouverte',
    date_creation  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY index_dates (date_debut),
    CONSTRAINT fk_mission_auteur     FOREIGN KEY (auteur_id)     REFERENCES membres (id)     ON DELETE CASCADE,
    CONSTRAINT fk_mission_competence FOREIGN KEY (competence_id) REFERENCES competences (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 2. Membres choisis pour une mission « ciblée »
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS missions_cibles (
    mission_id  INT UNSIGNED NOT NULL,
    membre_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (mission_id, membre_id),
    CONSTRAINT fk_cible_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cible_membre  FOREIGN KEY (membre_id)  REFERENCES membres (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3. Candidatures
--    statut :
--      'en_attente' → le membre s'est proposé, l'auteur n'a pas encore répondu
--      'acceptee'   → l'auteur a accepté : la mission est CONFIRMÉE pour ce membre
--                     (les deux personnes ont validé)
--      'refusee'    → l'auteur a refusé
--      'retiree'    → le membre a retiré sa candidature
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidatures (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mission_id        INT UNSIGNED NOT NULL,
    membre_id         INT UNSIGNED NOT NULL,
    message           TEXT         NULL,
    statut            ENUM('en_attente', 'acceptee', 'refusee', 'retiree') NOT NULL DEFAULT 'en_attente',
    date_creation     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_reponse      DATETIME     NULL,

    PRIMARY KEY (id),
    UNIQUE KEY une_candidature_par_membre (mission_id, membre_id),
    CONSTRAINT fk_candidature_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE,
    CONSTRAINT fk_candidature_membre  FOREIGN KEY (membre_id)  REFERENCES membres (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
