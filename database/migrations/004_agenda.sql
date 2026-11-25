-- ==========================================================================
-- KRAB — database/migrations/004_agenda.sql
-- --------------------------------------------------------------------------
-- Ajoute l'agenda :
--   - les disponibilités des membres (créneaux horaires) ;
--   - les invitations (l'auteur d'une mission invite un membre).
--
-- À exécuter UNE fois dans phpMyAdmin (onglet SQL), après 003_missions.sql.
-- ==========================================================================

USE krab;

-- --------------------------------------------------------------------------
-- 1. Disponibilités
--    visibilite (choisie pour CHAQUE créneau) :
--      'privee'  → visible par moi seul
--      'membres' → visible par tous les membres
--      'ciblee'  → visible seulement par les membres choisis
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS disponibilites (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    membre_id    INT UNSIGNED NOT NULL,
    date_jour    DATE         NOT NULL,
    heure_debut  TIME         NOT NULL,
    heure_fin    TIME         NOT NULL,
    visibilite   ENUM('privee', 'membres', 'ciblee') NOT NULL DEFAULT 'membres',

    PRIMARY KEY (id),
    KEY index_jour (date_jour),
    CONSTRAINT fk_dispo_membre FOREIGN KEY (membre_id) REFERENCES membres (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membres choisis pour un créneau « ciblé »
CREATE TABLE IF NOT EXISTS disponibilites_cibles (
    disponibilite_id  INT UNSIGNED NOT NULL,
    membre_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (disponibilite_id, membre_id),
    CONSTRAINT fk_dcible_dispo  FOREIGN KEY (disponibilite_id) REFERENCES disponibilites (id) ON DELETE CASCADE,
    CONSTRAINT fk_dcible_membre FOREIGN KEY (membre_id)        REFERENCES membres (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 2. Horaires des missions (facultatifs)
--    Servent à trouver les membres disponibles AU MOMENT de la mission.
-- --------------------------------------------------------------------------
ALTER TABLE missions
    ADD COLUMN heure_debut TIME NULL AFTER date_fin,
    ADD COLUMN heure_fin   TIME NULL AFTER heure_debut;

-- --------------------------------------------------------------------------
-- 3. Invitations
--    Une invitation est une « candidature » créée par l'auteur de la
--    mission, avec le nouvel état 'invitee'. Quand le membre invité
--    accepte, elle passe à 'acceptee' : la mission est confirmée.
-- --------------------------------------------------------------------------
ALTER TABLE candidatures
    MODIFY COLUMN statut ENUM('en_attente', 'invitee', 'acceptee', 'refusee', 'retiree')
                  NOT NULL DEFAULT 'en_attente',
    ADD COLUMN origine ENUM('candidature', 'invitation') NOT NULL DEFAULT 'candidature' AFTER membre_id;
