-- =============================================================================
-- DayOut — Seed de developpement
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0 compatible
--
-- Mot de passe commun a tous les comptes seedes : Password!2026
-- Hash bcrypt (cost 10) : $2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6
--
-- NOTE : certains events "passes" sont inseres en 'published' puis UPDATE
-- vers 'archived' en fin de script (sinon trg_registrations_bi refuse
-- les inscriptions sur un event non-'published').
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. SCHOOLS (15 ecoles Ile-de-France + 1 province)
-- -----------------------------------------------------------------------------
INSERT INTO schools (id, name, short_name, email_domain, city, active) VALUES
  (1,  'ISEP - Institut Superieur d''Electronique de Paris', 'ISEP',          'isep.fr',             'Issy-les-Moulineaux', 1),
  (2,  'EPITA',                                              'EPITA',         'epita.fr',            'Le Kremlin-Bicetre',  1),
  (3,  'EPITECH',                                            'EPITECH',       'epitech.eu',          'Paris',               1),
  (4,  'CentraleSupelec',                                    'CentraleSupelec','student-cs.fr',      'Gif-sur-Yvette',      1),
  (5,  'Ecole Polytechnique',                                'X',             'polytechnique.edu',   'Palaiseau',           1),
  (6,  'Telecom Paris',                                      'Telecom Paris', 'telecom-paris.fr',    'Palaiseau',           1),
  (7,  'Telecom SudParis',                                   'TSP',           'telecom-sudparis.eu', 'Evry',                1),
  (8,  'ENSTA Paris',                                        'ENSTA',         'ensta-paris.fr',      'Palaiseau',           1),
  (9,  'Mines Paris',                                        'Mines',         'minesparis.psl.eu',   'Paris',               1),
  (10, 'Ecole des Ponts ParisTech',                          'Ponts',         'enpc.fr',             'Marne-la-Vallee',     1),
  (11, 'ESIEE Paris',                                        'ESIEE',         'esiee.fr',            'Noisy-le-Grand',      1),
  (12, 'ESILV',                                              'ESILV',         'devinci.fr',          'Courbevoie',          1),
  (13, 'EFREI Paris',                                        'EFREI',         'efrei.net',           'Villejuif',           1),
  (14, 'UTC - Compiegne',                                    'UTC',           'etu.utc.fr',          'Compiegne',           1),
  (15, 'INSA Lyon',                                          'INSA Lyon',     'insa-lyon.fr',        'Villeurbanne',        1);

-- -----------------------------------------------------------------------------
-- 2. CATEGORIES
-- -----------------------------------------------------------------------------
INSERT INTO categories (id, slug, label, icon, active) VALUES
  (1,  'soiree',       'Soiree',           'party',       1),
  (2,  'concert',      'Concert',          'music',       1),
  (3,  'conference',   'Conference',       'mic',         1),
  (4,  'sport',        'Sport',            'activity',    1),
  (5,  'culture',      'Culture',          'book',        1),
  (6,  'gaming',       'Gaming / Esport',  'gamepad',     1),
  (7,  'tech',         'Tech / Hackathon', 'cpu',         1),
  (8,  'bde',          'BDE / Assoc',      'users',       1),
  (9,  'exterieur',    'Exterieur',        'tree',        1),
  (10, 'gastronomie',  'Gastronomie',      'utensils',    1);

-- -----------------------------------------------------------------------------
-- 3. USERS (14 au total : 1 admin + 3 createurs approuves + 1 pending + 8 participants)
--    Password commun : Password!2026
-- -----------------------------------------------------------------------------
INSERT INTO users
  (id, email, email_domain, password_hash, first_name, last_name, birth_date, phone,
   school_id, role, creator_status, email_verified, email_verified_at, reliability_score)
VALUES
  -- Admin (role=admin : trigger ne verifie pas la whitelist)
  (1,  'admin@dayout.local',   'dayout.local',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Alice',  'Admin',      '1990-04-12', '+33600000001',
       NULL, 'admin', 'none', 1, NOW(), 100),

  -- Createurs approuves (ISEP / EPITA / EPITECH)
  (2,  'bde.isep@isep.fr',     'isep.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'BDE',    'ISEP',       '2001-09-01', '+33600000002',
       1, 'creator', 'approved', 1, NOW(), 100),
  (3,  'orga.epita@epita.fr',  'epita.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Clement','Epita',      '2000-05-21', '+33600000003',
       2, 'creator', 'approved', 1, NOW(), 100),
  (4,  'events@epitech.eu',    'epitech.eu',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Diane',  'Epitech',    '2001-02-14', '+33600000004',
       3, 'creator', 'approved', 1, NOW(), 100),

  -- Createur en attente de validation
  (5,  'pending@efrei.net',    'efrei.net',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Enzo',   'Pending',    '2002-11-30', '+33600000005',
       13, 'participant', 'pending', 1, NOW(), 100),

  -- Participants (ISEP)
  (6,  'yovanne@isep.fr',      'isep.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Yovanne','Ponnou',     '2003-03-15', '+33600000006',
       1, 'participant', 'none', 1, NOW(), 100),
  (7,  'leo.martin@isep.fr',   'isep.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Leo',    'Martin',     '2003-07-22', '+33600000007',
       1, 'participant', 'none', 1, NOW(), 95),
  (8,  'maya.dupont@isep.fr',  'isep.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Maya',   'Dupont',     '2003-01-05', '+33600000008',
       1, 'participant', 'none', 1, NOW(), 90),

  -- Participants autres ecoles
  (9,  'ines.rousseau@epita.fr','epita.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Ines',   'Rousseau',   '2002-10-11', '+33600000009',
       2, 'participant', 'none', 1, NOW(), 85),
  (10, 'arthur.chen@epitech.eu','epitech.eu',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Arthur', 'Chen',       '2002-06-08', '+33600000010',
       3, 'participant', 'none', 1, NOW(), 80),
  (11, 'sofia.lopez@student-cs.fr','student-cs.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Sofia',  'Lopez',      '2003-12-19', '+33600000011',
       4, 'participant', 'none', 1, NOW(), 75),
  (12, 'tom.bernard@telecom-paris.fr','telecom-paris.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Tom',    'Bernard',    '2001-08-27', '+33600000012',
       6, 'participant', 'none', 1, NOW(), 70),

  -- Participant a faible fiabilite (score 30 -> ne peut plus s'inscrire)
  (13, 'risky@esiee.fr',       'esiee.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Ryan',   'Risky',      '2002-04-04', '+33600000013',
       11, 'participant', 'none', 1, NOW(), 30),

  -- Participant non verifie
  (14, 'unverified@devinci.fr', 'devinci.fr',
       '$2b$10$q6RtXwb8LDjOlbqdZ4wARe/aPP0VfqskrSN1ex9ErlQ47C0505IP6',
       'Nora',   'Unverified', '2003-11-02', '+33600000014',
       12, 'participant', 'none', 0, NULL, 100);

-- -----------------------------------------------------------------------------
-- 4. CREATOR PROFILES (4 : les 3 approuves + 1 pending)
-- -----------------------------------------------------------------------------
INSERT INTO creator_profiles
  (user_id, display_name, description, website_url, instagram_handle,
   submitted_at, reviewed_at, reviewed_by, review_notes)
VALUES
  (2, 'BDE ISEP',       'Bureau des Eleves de l''ISEP - soirees, voyages, afterworks.',
      'https://bde.isep.fr',   '@bde_isep',
      '2025-09-01 10:00:00', '2025-09-02 09:30:00', 1, 'Validated - official BDE account.'),
  (3, 'Orga EPITA',     'Equipe evenementielle de l''EPITA.',
      'https://assos.epita.fr',' @orga_epita',
      '2025-09-03 14:00:00', '2025-09-03 18:00:00', 1, 'Approved.'),
  (4, 'Epitech Events', 'Pole evenementiel Epitech Paris.',
      'https://events.epitech.eu','@epitech_events',
      '2025-09-05 11:20:00', '2025-09-06 10:15:00', 1, 'Approved.'),
  (5, 'EFREI Afterwork','Candidat organisateur - afterworks EFREI.',
      NULL, '@efrei_after',
      '2026-10-10 16:00:00', NULL, NULL, NULL);

-- -----------------------------------------------------------------------------
-- 5. USER INTERESTS (quelques exemples)
-- -----------------------------------------------------------------------------
INSERT INTO user_interests (user_id, category_id) VALUES
  (6, 1), (6, 2), (6, 7),            -- Yovanne : soiree, concert, tech
  (7, 1), (7, 4),                    -- Leo : soiree, sport
  (8, 3), (8, 5), (8, 10),           -- Maya : conference, culture, gastro
  (9, 6), (9, 7),                    -- Ines : gaming, tech
  (10, 2), (10, 7),                  -- Arthur : concert, tech
  (11, 3), (11, 5),                  -- Sofia : conference, culture
  (12, 4), (12, 9);                  -- Tom : sport, exterieur

-- -----------------------------------------------------------------------------
-- 6. EVENTS (10 : futurs, passes, payants, reserves ecole)
--    Dates ancrees sur "aujourd'hui" (2026-10-19) : futurs & passes.
-- -----------------------------------------------------------------------------
INSERT INTO events
  (id, creator_id, title, slug, description, cover_image_url,
   start_at, end_at, venue_name, address_line, city, postal_code,
   capacity, price_cents, currency, is_paid, external_ticket_url,
   school_restricted, restricted_school_id, status, published_at)
VALUES
  -- 1. Soiree de rentree ISEP (futur, gratuit, reserve ISEP)
  (1, 2, 'Soiree de rentree ISEP 2026', 'soiree-rentree-isep-2026',
   'La soiree qui lance l''annee a l''ISEP. DJ set, open bar soft, animations.', NULL,
   '2026-11-07 20:00:00','2026-11-08 02:00:00',
   'Le Rooftop', '28 rue Notre-Dame-des-Victoires', 'Paris', '75002',
   250, 0, 'EUR', 0, NULL,
   1, 1, 'published', '2026-10-01 09:00:00'),

  -- 2. Conference IA & Societe (futur, gratuit, ouvert)
  (2, 3, 'Conference : IA, emploi et societe', 'conf-ia-emploi-societe',
   'Table ronde avec chercheurs INRIA et DRH tech sur l''impact de l''IA.', NULL,
   '2026-11-14 18:30:00','2026-11-14 20:30:00',
   'Amphi Jaures', '14-16 rue Voltaire', 'Le Kremlin-Bicetre', '94270',
   180, 0, 'EUR', 0, NULL,
   0, NULL, 'published', '2026-10-05 10:00:00'),

  -- 3. Hackathon 48h (futur, payant 15 EUR, ouvert)
  (3, 4, 'Hackathon DayOut 48h', 'hackathon-dayout-48h',
   '48h non-stop pour pitcher la meilleure idee etudiante. Jury, prix, pizzas.', NULL,
   '2026-11-21 09:00:00','2026-11-23 09:00:00',
   'Epitech Paris', '14-16 rue Voltaire', 'Paris', '75011',
   120, 1500, 'EUR', 1, 'https://billetterie.example.com/hackathon-dayout',
   0, NULL, 'published', '2026-10-08 12:00:00'),

  -- 4. Concert jazz etudiant (futur, payant 8 EUR, ouvert)
  (4, 3, 'Concert Jazz Etudiant', 'concert-jazz-etudiant',
   'Scene ouverte jazz avec les formations etudiantes des conservatoires.', NULL,
   '2026-11-28 20:00:00','2026-11-28 23:30:00',
   'Petit Bain', '7 Port de la Gare', 'Paris', '75013',
   200, 800, 'EUR', 1, 'https://billetterie.example.com/jazz-etu',
   0, NULL, 'published', '2026-10-10 14:00:00'),

  -- 5. Tournoi LoL inter-ecoles (futur, gratuit, ouvert)
  (5, 4, 'Tournoi League of Legends inter-ecoles', 'tournoi-lol-inter-ecoles',
   'Tournoi 5v5 BO3 entre ecoles d''ingenieurs. Stream Twitch officiel.', NULL,
   '2026-12-05 10:00:00','2026-12-05 20:00:00',
   'Esport Arena', '45 rue de la Victoire', 'Paris', '75009',
   160, 0, 'EUR', 0, NULL,
   0, NULL, 'published', '2026-10-11 09:00:00'),

  -- 6. Afterwork BDE ISEP (futur, gratuit, reserve ISEP)
  (6, 2, 'Afterwork BDE ISEP', 'afterwork-bde-isep-nov',
   'Afterwork mensuel du BDE au bar partenaire.', NULL,
   '2026-10-30 19:00:00','2026-10-30 23:00:00',
   'Le Comptoir', '12 avenue Pasteur', 'Issy-les-Moulineaux', '92130',
   80, 0, 'EUR', 0, NULL,
   1, 1, 'published', '2026-10-12 11:00:00'),

  -- 7. Course a pied inter-campus (futur, gratuit, ouvert)
  (7, 2, 'Course inter-campus 10km', 'course-inter-campus-10km',
   '10 km solidaire reliant plusieurs campus. T-shirt offert.', NULL,
   '2026-12-12 09:30:00','2026-12-12 12:30:00',
   'Parc Suzanne-Lenglen', 'Boulevard des Freres-Voisin', 'Paris', '75015',
   300, 0, 'EUR', 0, NULL,
   0, NULL, 'published', '2026-10-13 10:00:00'),

  -- 8. Event passe (deja attendue - sera UPDATE -> archived)
  (8, 3, 'Soiree Halloween EPITA', 'soiree-halloween-epita-2026',
   'Soiree deguisee, concours du meilleur costume.', NULL,
   '2026-10-10 20:00:00','2026-10-11 02:00:00',
   'Salle des fetes', '14-16 rue Voltaire', 'Le Kremlin-Bicetre', '94270',
   150, 0, 'EUR', 0, NULL,
   0, NULL, 'published', '2026-09-20 10:00:00'),

  -- 9. Event passe (sera UPDATE -> archived)
  (9, 4, 'Meetup Dev Web Octobre', 'meetup-dev-web-oct-2026',
   'Meetup mensuel dev web - React, Node, bonnes pratiques.', NULL,
   '2026-10-05 19:00:00','2026-10-05 22:00:00',
   'Station F', '5 Parvis Alan Turing', 'Paris', '75013',
   100, 0, 'EUR', 0, NULL,
   0, NULL, 'published', '2026-09-15 10:00:00'),

  -- 10. Draft (brouillon : pas inscriptible)
  (10, 2, 'Gala BDE ISEP 2027 (brouillon)', 'gala-bde-isep-2027',
   'En preparation - gala annuel du BDE ISEP.', NULL,
   '2027-03-14 20:00:00','2027-03-15 03:00:00',
   'Pavillon Vendome', 'A definir', 'Paris', '75000',
   400, 4500, 'EUR', 1, NULL,
   1, 1, 'draft', NULL);

-- -----------------------------------------------------------------------------
-- 7. EVENT CATEGORIES
-- -----------------------------------------------------------------------------
INSERT INTO event_categories (event_id, category_id) VALUES
  (1, 1), (1, 8),       -- Soiree rentree : soiree + BDE
  (2, 3), (2, 7),       -- Conf IA : conference + tech
  (3, 7),               -- Hackathon : tech
  (4, 2), (4, 5),       -- Concert jazz : concert + culture
  (5, 6),               -- Tournoi LoL : gaming
  (6, 1), (6, 8),       -- Afterwork : soiree + BDE
  (7, 4), (7, 9),       -- Course : sport + exterieur
  (8, 1),               -- Halloween : soiree
  (9, 7),               -- Meetup : tech
  (10, 1), (10, 8);     -- Gala : soiree + BDE

-- -----------------------------------------------------------------------------
-- 8. REGISTRATIONS (16 au total)
--    QR hashes : SHA-256 hex factices mais UNIQUE.
-- -----------------------------------------------------------------------------
INSERT INTO registrations
  (id, event_id, user_id, status, qr_code_hash, registered_at, cancelled_at, checked_in_at)
VALUES
  -- Event 1 : Soiree rentree ISEP (reserve ISEP) - 4 inscrits ISEP
  (1,  1,  6,  'registered', 'a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1', '2026-10-02 10:00:00', NULL, NULL),
  (2,  1,  7,  'registered', 'a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', '2026-10-02 10:15:00', NULL, NULL),
  (3,  1,  8,  'registered', 'a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3', '2026-10-02 10:30:00', NULL, NULL),
  (4,  1,  2,  'registered', 'a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4', '2026-10-02 11:00:00', NULL, NULL),

  -- Event 2 : Conference IA (ouvert) - 3 inscrits de divers campus
  (5,  2,  6,  'registered', 'b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1b1', '2026-10-06 09:00:00', NULL, NULL),
  (6,  2,  10, 'registered', 'b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2', '2026-10-06 09:15:00', NULL, NULL),
  (7,  2,  11, 'registered', 'b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3b3', '2026-10-06 09:30:00', NULL, NULL),

  -- Event 3 : Hackathon (payant) - 2 inscrits
  (8,  3,  9,  'registered', 'c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1', '2026-10-09 10:00:00', NULL, NULL),
  (9,  3,  10, 'registered', 'c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2c2', '2026-10-09 10:30:00', NULL, NULL),

  -- Event 4 : Concert jazz (payant) - 1 inscrit
  (10, 4, 11,  'registered', 'd1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1d1', '2026-10-11 20:00:00', NULL, NULL),

  -- Event 5 : Tournoi LoL - 2 inscrits
  (11, 5,  9,  'registered', 'e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1e1', '2026-10-12 09:00:00', NULL, NULL),
  (12, 5, 10,  'registered', 'e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2e2', '2026-10-12 09:10:00', NULL, NULL),

  -- Event 6 : Afterwork ISEP - 2 inscrits ISEP
  (13, 6,  6,  'registered', 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1', '2026-10-13 11:00:00', NULL, NULL),
  (14, 6,  7,  'registered', 'f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2', '2026-10-13 11:15:00', NULL, NULL),

  -- Event 8 : Halloween EPITA (passe) - 1 attended + 1 no_show
  (15, 8,  9,  'attended', '080808080808080808080808080808080808080808080808080808080808a8a8', '2026-09-25 10:00:00', NULL, '2026-10-10 20:30:00'),
  (16, 8,  7,  'no_show',  '080808080808080808080808080808080808080808080808080808080808b8b8', '2026-09-28 15:00:00', NULL, NULL);

-- -----------------------------------------------------------------------------
-- 9. PAID EVENT CLICKS (billetterie externe : 0.5 participation dans le score)
-- -----------------------------------------------------------------------------
INSERT INTO paid_event_clicks (event_id, user_id, clicked_at) VALUES
  (3,  9, '2026-10-09 10:05:00'),
  (3, 10, '2026-10-09 10:35:00'),
  (3, 12, '2026-10-15 18:00:00'),  -- Tom a cliqué sans se re-inscrire
  (4, 11, '2026-10-11 20:05:00'),
  (4,  8, '2026-10-14 12:00:00');

-- -----------------------------------------------------------------------------
-- 10. ATTENDANCE EVENTS (journal fiabilite)
-- -----------------------------------------------------------------------------
INSERT INTO attendance_events (user_id, event_id, kind, score_delta, weight, note) VALUES
  (9,  8, 'attended',   2,  1.00, 'Scan QR a 20:30'),
  (7,  8, 'no_show',   -5,  1.00, 'Pas de scan QR'),
  (9,  3, 'paid_click', 0,  0.50, 'Clic billetterie Hackathon'),
  (10, 3, 'paid_click', 0,  0.50, 'Clic billetterie Hackathon'),
  (11, 4, 'paid_click', 0,  0.50, 'Clic billetterie concert jazz'),
  -- Historique Ryan (user 13) -> score ramene a 30
  (13, 8, 'no_show',   -5,  1.00, 'Absence Halloween'),
  (13, 9, 'no_show',   -5,  1.00, 'Absence Meetup'),
  (13, 2, 'cancelled_late', -10, 1.00, 'Annulation moins de 24h');

-- -----------------------------------------------------------------------------
-- 11. ABSENCE CORRECTIONS (1 demande, acceptee)
-- -----------------------------------------------------------------------------
INSERT INTO absence_corrections
  (registration_id, reason, proof_url, status, reviewed_by, reviewed_at, review_notes)
VALUES
  (16, 'Urgence familiale, j''ai un justificatif medical.',
      'https://files.example.com/leo-justif.pdf',
      'accepted', 1, '2026-10-12 09:00:00', 'Justificatif valide');

-- Journal : correction appliquee pour Leo (compensation du no_show)
INSERT INTO attendance_events (user_id, event_id, kind, score_delta, weight, note)
VALUES (7, 8, 'corrected', 5, 1.00, 'Correction absence acceptee par admin');

-- -----------------------------------------------------------------------------
-- 12. PLANNINGS (1 planning pour Yovanne avec 2 items)
-- -----------------------------------------------------------------------------
INSERT INTO plannings (id, user_id, name) VALUES
  (1, 6, 'Mon planning DayOut');

INSERT INTO planning_items (planning_id, event_id, title, start_at, end_at, note) VALUES
  (1, 1,  NULL,                       '2026-11-07 20:00:00', '2026-11-08 02:00:00', 'Soiree rentree ISEP'),
  (1, 2,  NULL,                       '2026-11-14 18:30:00', '2026-11-14 20:30:00', 'Conference IA'),
  (1, NULL,'Revisions partiels S1',   '2026-11-20 14:00:00', '2026-11-20 18:00:00', 'Perso');

-- -----------------------------------------------------------------------------
-- 13. SUGGESTION DISMISSALS
-- -----------------------------------------------------------------------------
INSERT INTO suggestion_dismissals (user_id, event_id, dismissed_at) VALUES
  (6, 5, '2026-10-14 10:00:00'),
  (8, 3, '2026-10-14 11:00:00');

-- -----------------------------------------------------------------------------
-- 14. EVENT MESSAGES (chat d'event)
-- -----------------------------------------------------------------------------
INSERT INTO event_messages (event_id, user_id, body, created_at) VALUES
  (1, 2, 'Hey ! On a hate de vous voir. Dress code : festif.', '2026-10-03 09:00:00'),
  (1, 6, 'Trop hate ! Qui prend le metro depuis Issy ?',        '2026-10-03 09:30:00'),
  (3, 4, 'Les equipes seront formees sur place le vendredi matin.', '2026-10-10 11:00:00'),
  (3, 9, 'Possible de venir seule ?',                                '2026-10-10 11:15:00'),
  (6, 7, 'Je ramene qui veut en voiture depuis l''ISEP.',            '2026-10-14 12:00:00');

-- -----------------------------------------------------------------------------
-- 15. REPORTS + ACTIONS (1 signalement + 1 action admin)
-- -----------------------------------------------------------------------------
INSERT INTO reports
  (id, reporter_id, target_type, target_event_id, target_user_id, target_message_id,
   reason, details, status, created_at)
VALUES
  (1, 8, 'message', NULL, NULL, 4,
   'spam', 'Message hors-sujet et insistant.', 'resolved', '2026-10-10 12:00:00');

INSERT INTO report_actions (report_id, admin_id, action, comment, created_at) VALUES
  (1, 1, 'warn', 'Avertissement envoye a l''utilisateur, message conserve.', '2026-10-10 14:00:00');

-- -----------------------------------------------------------------------------
-- 16. FAQ
-- -----------------------------------------------------------------------------
INSERT INTO faq_entries (question, answer, sort_order, active) VALUES
  ('Comment creer un compte ?',
   'Utilise ton email etudiant (@isep.fr, @epita.fr, ...). Un lien de verification te sera envoye.',
   1, 1),
  ('Pourquoi mon ecole n''est pas acceptee ?',
   'La liste des ecoles est en whitelist. Contacte-nous pour qu''elle soit ajoutee.',
   2, 1),
  ('Qu''est-ce que le score de fiabilite ?',
   'Un indicateur base sur ta participation reelle aux evenements. En dessous de 40, tu ne peux plus t''inscrire.',
   3, 1),
  ('Comment devenir createur d''evenements ?',
   'Depose une demande depuis ton profil. Un admin la validera sous 48h.',
   4, 1),
  ('Les evenements payants comptent-ils dans ma fiabilite ?',
   'Oui : un clic vers la billetterie compte pour 0,5 participation.',
   5, 1);

-- -----------------------------------------------------------------------------
-- 17. LEGAL PAGES
-- -----------------------------------------------------------------------------
INSERT INTO legal_pages (slug, title, body_md) VALUES
  ('cgu',             'Conditions Generales d''Utilisation',
   '# CGU DayOut\n\nEn utilisant DayOut, vous acceptez les presentes conditions...'),
  ('privacy',         'Politique de confidentialite',
   '# Vie privee\n\nDayOut collecte le minimum de donnees necessaire au service...'),
  ('mentions-legales','Mentions legales',
   '# Mentions legales\n\nDayOut est un projet etudiant ISEP A1 Dev Web...'),
  ('cookies',         'Cookies',
   '# Cookies\n\nDayOut utilise uniquement des cookies techniques (session).');

-- -----------------------------------------------------------------------------
-- 18. CONTACT MESSAGES (1 non traite + 1 traite)
-- -----------------------------------------------------------------------------
INSERT INTO contact_messages (user_id, email, subject, body, handled, handled_at, handled_by, created_at) VALUES
  (6,    'yovanne@isep.fr', 'Bug sur le planning',
   'Impossible d''ajouter un item perso, le bouton ne reagit pas.',
   0, NULL, NULL, '2026-10-15 14:00:00'),
  (NULL, 'prospect@utc.fr', 'Ajout de l''UTC',
   'Bonjour, pouvez-vous ajouter l''UTC a la liste des ecoles ?',
   1, '2026-10-10 10:00:00', 1, '2026-10-09 18:00:00');

-- -----------------------------------------------------------------------------
-- 19. ARCHIVAGE DES EVENTS PASSES
-- -----------------------------------------------------------------------------
-- IMPORTANT : events 8 et 9 ont ete inseres en 'published' pour permettre les
-- inscriptions seedees (trg_registrations_bi exige status='published').
-- On les bascule maintenant en 'archived'.
UPDATE events SET status = 'archived' WHERE id IN (8, 9);

COMMIT;

-- =============================================================================
-- Fin de 02_seed.sql
-- Resume : 15 schools, 10 categories, 14 users, 10 events, 16 registrations
-- =============================================================================
