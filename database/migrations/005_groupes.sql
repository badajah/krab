-- ==========================================================================
-- KRAB — database/migrations/005_groupes.sql
-- --------------------------------------------------------------------------
-- Ajoute les GROUPES PERSONNELS de membres.
--
-- Chaque membre crée ses propres groupes (ex. « Équipe chantier »).
-- Ils ne sont visibles que par lui. Quand une mission ou une disponibilité
-- est « Membres choisis », on peut la cibler sur des groupes et/ou des
-- personnes.
--
-- Les groupes sont « vivants » : un membre ajouté plus tard à un groupe
-- voit aussitôt ce qui est ciblé sur ce groupe ; un membre retiré ne le
-- voit plus.
--
-- À exécuter UNE fois dans phpMyAdmin (onglet SQL), après 004_agenda.sql.
-- ==========================================================================

USE krab;

-- Les groupes
CREATE TABLE IF NOT EXISTS groupes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    proprietaire_id  INT UNSIGNED NOT NULL,          -- le membre qui a créé le groupe
    nom              VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_groupe_proprietaire FOREIGN KEY (proprietaire_id) REFERENCES membres (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les membres de chaque groupe
CREATE TABLE IF NOT EXISTS groupes_membres (
    groupe_id  INT UNSIGNED NOT NULL,
    membre_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (groupe_id, membre_id),
    CONSTRAINT fk_gm_groupe FOREIGN KEY (groupe_id) REFERENCES groupes (id) ON DELETE CASCADE,
    CONSTRAINT fk_gm_membre FOREIGN KEY (membre_id) REFERENCES membres (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groupes choisis pour une mission « Membres choisis »
CREATE TABLE IF NOT EXISTS missions_groupes (
    mission_id  INT UNSIGNED NOT NULL,
    groupe_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (mission_id, groupe_id),
    CONSTRAINT fk_mg_mission FOREIGN KEY (mission_id) REFERENCES missions (id) ON DELETE CASCADE,
    CONSTRAINT fk_mg_groupe  FOREIGN KEY (groupe_id)  REFERENCES groupes (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groupes choisis pour une disponibilité « Membres choisis »
CREATE TABLE IF NOT EXISTS disponibilites_groupes (
    disponibilite_id  INT UNSIGNED NOT NULL,
    groupe_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (disponibilite_id, groupe_id),
    CONSTRAINT fk_dg_dispo  FOREIGN KEY (disponibilite_id) REFERENCES disponibilites (id) ON DELETE CASCADE,
    CONSTRAINT fk_dg_groupe FOREIGN KEY (groupe_id)        REFERENCES groupes (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
