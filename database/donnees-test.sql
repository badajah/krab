-- ==========================================================================
-- KRAB — database/donnees-test.sql
-- --------------------------------------------------------------------------
-- DONNÉES DE TEST : à utiliser UNIQUEMENT sur votre ordinateur (MAMP),
-- JAMAIS sur le site en ligne.
--
-- Ajoute 3 membres fictifs, avec profils, compétences, disponibilités,
-- missions et candidatures dans différents états, et vous implique
-- (vous = le premier compte qui n'est pas un compte de test) :
--   - une candidature de votre part en attente ;
--   - une invitation à accepter ou refuser ;
--   - une mission « membres choisis » visible par vous ;
--   - des candidatures reçues sur VOS missions ouvertes (s'il y en a).
--
-- Les 3 comptes de test (mot de passe pour tous : krab-test-2026) :
--   camille@krab.test   luca@krab.test   nina@krab.test
--
-- Le fichier peut être relancé autant de fois que nécessaire :
-- il supprime d'abord les anciennes données de test.
-- Pour tout effacer : exécuter seulement la partie « 0. NETTOYAGE ».
--
-- Toutes les dates sont calculées à partir d'aujourd'hui (CURDATE()),
-- elles sont donc toujours dans le futur.
--
-- Prérequis : migrations 001 à 004 exécutées.
-- ==========================================================================

USE krab;

-- --------------------------------------------------------------------------
-- 0. NETTOYAGE : supprime les comptes de test et tout ce qui leur est lié
--    (missions, candidatures, disponibilités... grâce à ON DELETE CASCADE)
-- --------------------------------------------------------------------------
DELETE FROM membres WHERE email LIKE '%@krab.test';

-- Vous : le premier compte qui n'est pas un compte de test
SET @moi = (SELECT MIN(id) FROM membres WHERE email NOT LIKE '%@krab.test');


-- --------------------------------------------------------------------------
-- 1. Les 3 membres de test (mot de passe : krab-test-2026)
-- --------------------------------------------------------------------------
INSERT INTO membres (prenom, nom, email, mot_de_passe, presentation, localite, canton) VALUES
('Camille', 'Martin', 'camille@krab.test',
 '$2y$10$FT08yCj2wqBG435nGPM9N.euiVpgXwKPftboepNuOnFX63sTySTQW',
 'Graphiste indépendante depuis dix ans. J''aime les projets associatifs, les affiches et les petites éditions.',
 'Lausanne', 'VD'),
('Luca', 'Rossi', 'luca@krab.test',
 '$2y$10$FT08yCj2wqBG435nGPM9N.euiVpgXwKPftboepNuOnFX63sTySTQW',
 'Menuisier, je rénove des espaces collectifs. Toujours partant pour un chantier participatif.',
 'Sion', 'VS'),
('Nina', 'Keller', 'nina@krab.test',
 '$2y$10$FT08yCj2wqBG435nGPM9N.euiVpgXwKPftboepNuOnFX63sTySTQW',
 'Photographe et vidéaste. Je documente les luttes et les fêtes de quartier.',
 'Genève', 'GE');

SET @camille = (SELECT id FROM membres WHERE email = 'camille@krab.test');
SET @luca    = (SELECT id FROM membres WHERE email = 'luca@krab.test');
SET @nina    = (SELECT id FROM membres WHERE email = 'nina@krab.test');


-- --------------------------------------------------------------------------
-- 2. Compétences (3 maximum chacun)
-- --------------------------------------------------------------------------
INSERT INTO membres_competences (membre_id, competence_id)
SELECT @camille, id FROM competences WHERE nom IN ('Graphisme', 'Illustration', 'Mise en page');

INSERT INTO membres_competences (membre_id, competence_id)
SELECT @luca, id FROM competences WHERE nom IN ('Menuiserie', 'Bâtiment et rénovation', 'Peinture');

INSERT INTO membres_competences (membre_id, competence_id)
SELECT @nina, id FROM competences WHERE nom IN ('Photographie', 'Montage vidéo', 'Communication');


-- --------------------------------------------------------------------------
-- 3. Disponibilités sur les deux prochaines semaines
-- --------------------------------------------------------------------------
INSERT INTO disponibilites (membre_id, date_jour, heure_debut, heure_fin, visibilite) VALUES
-- Camille : plusieurs matinées, visibles par tous
(@camille, DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '08:00', '12:00', 'membres'),
(@camille, DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '08:00', '17:00', 'membres'),
(@camille, DATE_ADD(CURDATE(), INTERVAL 4 DAY),  '08:00', '12:00', 'membres'),
(@camille, DATE_ADD(CURDATE(), INTERVAL 10 DAY), '09:00', '18:00', 'membres'),
-- Luca : de longues journées, et un créneau privé (que vous ne devez PAS voir)
(@luca,    DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '07:00', '19:00', 'membres'),
(@luca,    DATE_ADD(CURDATE(), INTERVAL 5 DAY),  '07:00', '19:00', 'membres'),
(@luca,    DATE_ADD(CURDATE(), INTERVAL 6 DAY),  '07:00', '12:00', 'privee'),
-- Nina : après-midis, dont un réservé à vous seul (« membres choisis »)
(@nina,    DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '13:00', '19:00', 'membres'),
(@nina,    DATE_ADD(CURDATE(), INTERVAL 4 DAY),  '13:00', '19:00', 'ciblee'),
(@nina,    DATE_ADD(CURDATE(), INTERVAL 9 DAY),  '10:00', '18:00', 'membres');

-- Le créneau « ciblé » de Nina est visible par vous uniquement
INSERT INTO disponibilites_cibles (disponibilite_id, membre_id)
SELECT id, @moi FROM disponibilites
WHERE membre_id = @nina AND visibilite = 'ciblee';


-- --------------------------------------------------------------------------
-- 4. Missions
-- --------------------------------------------------------------------------

-- Mission 1 (Camille) : publique, 2 places, une déjà confirmée (Nina)
INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                      date_debut, date_fin, heure_debut, heure_fin, nombre_places, visibilite) VALUES
(@camille, 'Mise en page d''un fanzine',
 'Nous préparons le premier numéro d''un fanzine de quartier (24 pages). Il faut mettre en page les textes et les images, puis préparer le fichier pour l''imprimerie.',
 (SELECT id FROM competences WHERE nom = 'Mise en page'), 'Lausanne', 'VD',
 DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, '09:00', '12:00', 2, 'publique');
SET @m1 = LAST_INSERT_ID();

-- Mission 2 (Luca) : publique, sur 3 jours, 3 places
INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                      date_debut, date_fin, heure_debut, heure_fin, nombre_places, visibilite) VALUES
(@luca, 'Rénovation d''un atelier associatif',
 'Remise en état d''un atelier partagé : ponçage, peinture, montage d''établis. Repas de midi offert par l''association.',
 (SELECT id FROM competences WHERE nom = 'Bâtiment et rénovation'), 'Sion', 'VS',
 DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY), '08:00', '17:00', 3, 'publique');
SET @m2 = LAST_INSERT_ID();

-- Mission 3 (Nina) : « membres choisis » (vous et Camille), 1 place, déjà COMPLÈTE
INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                      date_debut, date_fin, heure_debut, heure_fin, nombre_places, visibilite) VALUES
(@nina, 'Affiches pour une fête de quartier',
 'Besoin d''une affiche et de quelques visuels pour les réseaux. Je fournis les photos.',
 (SELECT id FROM competences WHERE nom = 'Graphisme'), 'Genève', 'GE',
 DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, '14:00', '18:00', 1, 'ciblee');
SET @m3 = LAST_INSERT_ID();

INSERT INTO missions_cibles (mission_id, membre_id) VALUES (@m3, @moi), (@m3, @camille);

-- Mission 4 (Nina) : publique, 1 place, personne encore
INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                      date_debut, date_fin, heure_debut, heure_fin, nombre_places, visibilite) VALUES
(@nina, 'Tournage d''une courte vidéo',
 'Tournage de 2 heures pour présenter une coopérative alimentaire. Je cherche une personne pour tenir le son.',
 (SELECT id FROM competences WHERE nom = 'Son et musique'), 'Genève', 'GE',
 DATE_ADD(CURDATE(), INTERVAL 9 DAY), NULL, '10:00', '12:00', 1, 'publique');

-- Mission 5 (Camille) : privée (brouillon) — vous ne devez PAS la voir
INSERT INTO missions (auteur_id, titre, description, competence_id, localite, canton,
                      date_debut, date_fin, nombre_places, visibilite) VALUES
(@camille, 'Atelier sérigraphie (brouillon)',
 'Idée en cours : un atelier d''initiation à la sérigraphie.',
 (SELECT id FROM competences WHERE nom = 'Artisanat'), 'Lausanne', 'VD',
 DATE_ADD(CURDATE(), INTERVAL 12 DAY), NULL, 4, 'privee');


-- --------------------------------------------------------------------------
-- 5. Candidatures et invitations, dans tous les états
-- --------------------------------------------------------------------------
INSERT INTO candidatures (mission_id, membre_id, origine, statut, message, date_reponse) VALUES
-- Mission 1 : Nina confirmée, vous en attente
(@m1, @nina, 'candidature', 'acceptee',   'Je peux aussi faire les photos de couverture !', NOW()),
(@m1, @moi,  'candidature', 'en_attente', 'Je suis disponible toute la matinée.',          NULL),
-- Mission 2 : Camille confirmée, Nina refusée, VOUS INVITÉ·E (à accepter ou refuser)
(@m2, @camille, 'candidature', 'acceptee', 'Je viens les trois jours.', NOW()),
(@m2, @nina,    'candidature', 'refusee',  'Seulement le premier jour.', NOW()),
(@m2, @moi,     'invitation',  'invitee',  NULL, NULL),
-- Mission 3 : Camille confirmée → mission complète
(@m3, @camille, 'invitation', 'acceptee', NULL, NOW());

-- Vos missions ouvertes (s'il y en a) : Camille et Luca se proposent
INSERT IGNORE INTO candidatures (mission_id, membre_id, origine, statut, message)
SELECT id, @camille, 'candidature', 'en_attente', 'Bonjour, cette mission m''intéresse beaucoup !'
FROM missions WHERE auteur_id = @moi AND statut = 'ouverte';

INSERT IGNORE INTO candidatures (mission_id, membre_id, origine, statut, message)
SELECT id, @luca, 'candidature', 'en_attente', NULL
FROM missions WHERE auteur_id = @moi AND statut = 'ouverte';
