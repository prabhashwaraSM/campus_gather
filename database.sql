SET time_zone = '+05:30';

-- Create database
CREATE DATABASE IF NOT EXISTS campus_gather CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_gather;

-- Drop in reverse dependency order for clean re-imports if needed
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS section_members;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;

-- 1. Users table (Students & Organizers)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    student_id VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    department VARCHAR(120) DEFAULT 'Faculty of Information Technology',
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Events table
CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    category VARCHAR(50) NOT NULL,
    society VARCHAR(120) NOT NULL,
    starts_at DATETIME NOT NULL,
    venue VARCHAR(160) NOT NULL,
    organiser_name VARCHAR(120) NOT NULL,
    contact_email VARCHAR(190) NOT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    join_deadline DATE NOT NULL,
    status ENUM('Planning', 'Open for joining', 'In progress', 'Completed', 'Cancelled') DEFAULT 'Open for joining',
    description TEXT NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (starts_at),
    INDEX (category),
    INDEX (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Organizing sections table (Sub-teams within an event)
CREATE TABLE sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    leader_name VARCHAR(120) NOT NULL,
    leader_email VARCHAR(190) DEFAULT NULL,
    responsibilities TEXT NOT NULL,
    useful_skills VARCHAR(255) DEFAULT NULL,
    member_limit INT UNSIGNED DEFAULT 10,
    joining_status ENUM('Open', 'Closed') DEFAULT 'Open',
    resources TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Section membership and join applications
CREATE TABLE section_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    applicant_note TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_section_user (section_id, user_id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Section Tasks (To do, In progress, Done)
CREATE TABLE tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT DEFAULT NULL,
    assigned_to_user_id INT UNSIGNED DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    status ENUM('todo', 'in_progress', 'done') DEFAULT 'todo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================================
-- SEED DATA: Faculty of Information Technology, University of Moratuwa
-- Default password for all seed accounts is: Password123!
-- ==============================================================================

-- 1. Insert Demo Users
INSERT INTO users (id, name, student_id, email, password_hash, department, role) VALUES
(1, 'Kavindu Perera', '210001A', 'organizer@mora.ac.lk', '$2y$10$vrKJxdiRkd9g28rUorBNau6P0owiSucgLThcuD5K9UTMrvzjapwPq', 'Information Technology', 'admin'),
(2, 'Prabhashwara Dias', '220045B', 'student@mora.ac.lk', '$2y$10$vrKJxdiRkd9g28rUorBNau6P0owiSucgLThcuD5K9UTMrvzjapwPq', 'Information Technology', 'student'),
(3, 'Nisali Senaratne', '220112C', 'nisali@mora.ac.lk', '$2y$10$vrKJxdiRkd9g28rUorBNau6P0owiSucgLThcuD5K9UTMrvzjapwPq', 'Interdisciplinary Studies', 'student'),
(4, 'Charith Mendis', '220330D', 'charith@mora.ac.lk', '$2y$10$vrKJxdiRkd9g28rUorBNau6P0owiSucgLThcuD5K9UTMrvzjapwPq', 'Information Technology', 'student'),
(5, 'Dilini Fernando', '230089E', 'dilini@mora.ac.lk', '$2y$10$vrKJxdiRkd9g28rUorBNau6P0owiSucgLThcuD5K9UTMrvzjapwPq', 'Information Technology', 'student');

-- 2. Insert Events
INSERT INTO events (id, title, category, society, starts_at, venue, organiser_name, contact_email, contact_phone, join_deadline, status, description, created_by) VALUES
(1, 
 'FIT Future Sparks 2026: IT Tech Fest & 24h Hackathon', 
 'Technology', 
 'INTECS (Information Technology Society, UoM)', 
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 14 DAY), '08:30:00'), 
 'Faculty of IT Auditorium & Lab Complex, University of Moratuwa', 
 'Kavindu Perera (INTECS President)', 
 'intecs@mora.ac.lk', 
 '+94 77 123 4567', 
 DATE_ADD(CURDATE(), INTERVAL 7 DAY), 
 'Open for joining', 
 'FIT Future Sparks 2026 is the premier annual technology summit and 24-hour inter-batch hackathon organized by the Information Technology Society (INTECS) of the University of Moratuwa. The event brings together undergraduate developers, UI/UX designers, and industry leaders from top Sri Lankan tech firms.\n\nWe are actively recruiting student organizing teams across eight functional sections: Finance, Design, Publicity & Media, Logistics, Technical, Programme, Sponsorship, and Registration. Joining an organizing section offers hands-on operational leadership experience and networking opportunities.', 
 1),

(2, 
 'MoraXtreme: Inter-University Algorithmic Battle', 
 'Competition', 
 'IEEE Student Branch of University of Moratuwa', 
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 21 DAY), '13:00:00'), 
 'Computer Systems Lab 2 & Online Arena', 
 'Charith Mendis (Branch Chair)', 
 'ieee@mora.ac.lk', 
 '+94 71 987 6543', 
 DATE_ADD(CURDATE(), INTERVAL 12 DAY), 
 'Open for joining', 
 'A 12-hour high-intensity competitive programming hackathon challenging university coders with data structures, algorithm design, and algorithmic problem solving. Sections are open for Logistics, Technical Platform setup, and Sponsorship.', 
 1),

(3, 
 'IT Faculty Career Runway & Industry Connect', 
 'Workshop', 
 'Career Guidance Unit — Faculty of IT', 
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '09:00:00'), 
 'Main University Library Auditorium', 
 'Dr. Nilmini Silva (Senior Advisor)', 
 'cgu-fit@mora.ac.lk', 
 '+94 11 265 0301', 
 DATE_ADD(CURDATE(), INTERVAL 18 DAY), 
 'Planning', 
 'Comprehensive workshop series featuring CV clinics, mock technical interviews, and portfolio reviews conducted by engineering managers from leading tech companies.', 
 1);

-- 3. Insert All 8 Organizing Sections for Event 1 (FIT Future Sparks 2026)
INSERT INTO sections (id, event_id, name, description, leader_name, leader_email, responsibilities, useful_skills, member_limit, joining_status, resources) VALUES
(1, 1, 
 'Finance', 
 'Manages the overall event budget, tracks expenditures, reimburses verified receipts, and balances accounts.', 
 'Chamari Wickramasinghe', 
 'chamari.w@mora.ac.lk', 
 'Prepare detailed budget breakdowns; record venue and equipment expenses; collect and check vendor receipts; maintain cash float on event day; compile post-event financial report.', 
 'Excel / Google Sheets, basic budgeting accuracy, transparency. Beginners welcome!', 
 5, 
 'Open', 
 'Shared Budget Sheet: https://docs.google.com/spreadsheets/d/example-budget'),

(2, 1, 
 'Design', 
 'Creates the event visual brand identity, social media campaign graphics, badges, and certificates.', 
 'Prabhashwara Dias', 
 'student@mora.ac.lk', 
 'Design keynote announcement posters, Instagram/LinkedIn teaser carousels, stage backdrop, participant ID badges, and official digital certificates.', 
 'Figma, Adobe Illustrator, Canva, typography and color theory. Creative beginners welcome!', 
 6, 
 'Open', 
 'Brand Assets Drive: https://drive.google.com/drive/folders/example-design-assets'),

(3, 1, 
 'Publicity & Media', 
 'Drives faculty-wide promotion, writes engaging announcements, produces video reels, and handles photography.', 
 'Thilina Jayasuriya', 
 'thilina.j@mora.ac.lk', 
 'Draft social copy and schedule Facebook/LinkedIn/Instagram announcements; coordinate with batch representatives; shoot teaser reels; document live event photography.', 
 'Copywriting, social media handling, photography, video editing. Beginners welcome!', 
 8, 
 'Open', 
 'Publicity Schedule: https://docs.google.com/document/d/example-pub-plan'),

(4, 1, 
 'Logistics', 
 'Oversees venue arrangements, audio-visual equipment, table setup, power supplies, and banners.', 
 'Sahan Bandara', 
 'sahan.b@mora.ac.lk', 
 'Reserve auditorium and lab halls; arrange 35 team tables and 150 chairs; coordinate power extension boards; verify microphone and sound levels.', 
 'Team coordination, physical arrangement, punctual time management.', 
 10, 
 'Open', 
 'Floor Plan Blueprint: https://drive.google.com/file/d/example-floor-plan'),

(5, 1, 
 'Technical', 
 'Maintains the hackathon submission portal, Wi-Fi connectivity, dual-screen projectors, and live scoring.', 
 'Dineth Mendis', 
 'dineth.m@mora.ac.lk', 
 'Coordinate dedicated high-speed Wi-Fi with Network Center; deploy and monitor GitHub code submission portal; manage projector feeds during presentations.', 
 'Networking, Linux server admin, GitHub, AV equipment troubleshooting.', 
 6, 
 'Open', 
 'Technical Architecture Docs: https://github.com/fit-uom/hackathon-portal'),

(6, 1, 
 'Programme', 
 'Curates the schedule, drafts minute-by-minute rundown, and coordinates keynote speakers and judges.', 
 'Senuri Weerakkody', 
 'senuri.w@mora.ac.lk', 
 'Plan the complete 24h event itinerary; coordinate speaker briefing and arrival times; rehearse with stage MCs; manage judging panel schedules.', 
 'Agenda planning, public speaking, diplomatic coordination.', 
 5, 
 'Open', 
 'Rundown Document: https://docs.google.com/document/d/example-agenda'),

(7, 1, 
 'Sponsorship', 
 'Reaches out to corporate tech sponsors, tracks deliverables, and coordinates sponsor booths.', 
 'Oshada Fonseka', 
 'oshada.f@mora.ac.lk', 
 'Prepare corporate sponsorship proposals; follow up with industry partners; ensure logo placements on promotional materials; host sponsor representatives.', 
 'Professional email writing, negotiation, interpersonal communication.', 
 5, 
 'Open', 
 'Sponsorship Brochure PDF: https://drive.google.com/file/d/example-sponsorship-deck'),

(8, 1, 
 'Registration & Hospitality', 
 'Welcomes participants, manages badge check-in, distributes swags, and coordinates meals and refreshments.', 
 'Kaveen Ranasinghe', 
 'kaveen.r@mora.ac.lk', 
 'Manage participant check-in system; distribute welcome kits and wristbands; coordinate lunch, dinner, and mid-night refreshments for hackathon teams.', 
 'Hospitality, attention to detail, organization. Enthusiastic beginners welcome!', 
 8, 
 'Open', 
 'Participant Check-in Sheet: https://docs.google.com/spreadsheets/d/example-registration');

-- Sections for Event 2 (MoraXtreme)
INSERT INTO sections (id, event_id, name, description, leader_name, leader_email, responsibilities, useful_skills, member_limit, joining_status, resources) VALUES
(9, 2, 'Technical & Judges Panel', 'Problem setting and judge platform hosting.', 'Charith Mendis', 'charith@mora.ac.lk', 'Test problem test cases, host DOMjudge server.', 'C++, Python, Problem Setting', 4, 'Open', NULL),
(10, 2, 'Logistics & Refreshments', 'Lab reservations and snacks for participants.', 'Dilini Fernando', 'dilini@mora.ac.lk', 'Prepare computer lab seats and water bottles.', 'Event logistics', 6, 'Open', NULL);

-- 4. Insert Sample Section Members (Applications & Approved Members)
INSERT INTO section_members (section_id, user_id, applicant_note, status, applied_at, decided_at) VALUES
-- Design section for Event 1
(2, 2, 'Experienced with Figma, designed last year INTECS batch t-shirt.', 'approved', NOW(), NOW()),
(2, 3, 'Keen to learn social media post graphics and certificate design.', 'approved', NOW(), NOW()),
-- Publicity section for Event 1
(3, 4, 'Active on campus social channels and enjoy video editing.', 'approved', NOW(), NOW()),
(3, 2, 'Can help with writing LinkedIn and Facebook announcements.', 'approved', NOW(), NOW()),
-- Technical section for Event 1
(5, 5, 'Familiar with Linux, Docker, and setting up local network routers.', 'approved', NOW(), NOW()),
-- Finance section application (pending)
(1, 3, 'Interested in tracking budget and learning financial management.', 'pending', NOW(), NULL);

-- 5. Insert Sample Tasks across (To do, In progress, Done)
INSERT INTO tasks (section_id, title, description, assigned_to_user_id, deadline, status) VALUES
-- Design Section Tasks
(2, 'Finalize master event color palette and typography', 'Select high-contrast Royal Blue & White theme with accessible secondary tones.', 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'done'),
(2, 'Design social media launch poster and batch teasers', 'Export 1080x1080 and 1080x1920 stories for Instagram, Facebook, and LinkedIn.', 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'in_progress'),
(2, 'Create digital certificates and badge templates', 'Design Figma components for winner certificates and participant ID lanyards.', 3, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'todo'),

-- Finance Section Tasks
(1, 'Prepare provisional budget sheet with Dean approval', 'Itemize expenses for auditorium booking, sounds, food, and prizes.', 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'done'),
(1, 'Verify printing vendor quotation for banners', 'Compare prices between Moratuwa local print shops.', 1, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'in_progress'),
(1, 'Compile final financial statement and receipts', 'Collect all bills after hackathon completion for audit.', NULL, DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'todo'),

-- Publicity Section Tasks
(3, 'Draft announcement captions for batch WhatsApp groups', 'Short, engaging announcement inviting teams to participate.', 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'done'),
(3, 'Coordinate event photography with MoraSpirit unit', 'Confirm 2 student photographers for opening ceremony and hackathon night.', 4, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'in_progress'),
(3, 'Produce 30-second teaser reel for Instagram', 'Edit energetic video reel highlighting prize pool and tech challenges.', 2, DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'todo'),

-- Logistics Section Tasks
(4, 'Submit hall reservation request to Dean Faculty of IT', 'Get formal signed approval for Lab 2, Lab 3, and Auditorium.', NULL, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'done'),
(4, 'Inspect 40 power extension boards and network cables', 'Ensure each hackathon desk has sufficient power plugs.', NULL, DATE_ADD(CURDATE(), INTERVAL 9 DAY), 'in_progress'),
(4, 'Organize stage setup, podium, and sponsor standees', 'Arrange stage background and test microphones.', NULL, DATE_ADD(CURDATE(), INTERVAL 13 DAY), 'todo'),

-- Technical Section Tasks
(5, 'Liaise with Network Operations Center for 1Gbps uplink', 'Ensure continuous Wi-Fi and wired LAN in the lab complex.', 5, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'done'),
(5, 'Setup and stress-test coding submission server', 'Deploy automated judging environment and GitHub webhook triggers.', 5, DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'in_progress'),
(5, 'Configure dual-projector display feeds for presentations', 'Test HDMI switchers and presenter wireless remotes.', NULL, DATE_ADD(CURDATE(), INTERVAL 13 DAY), 'todo');
