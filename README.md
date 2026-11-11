# CampusGather · University Event & Section Management Portal
### Faculty of Information Technology · University of Moratuwa
**Course:** Web Technologies & Application Development (Assignment 02)  
**Tech Stack:** Native HTML5, CSS3, JavaScript (ES6+), PHP 8.2 (PDO), MySQL / MariaDB (XAMPP)  
**Theme:** High-Contrast Architectural White & Royal Blue (Inspired by the typographic clarity of TEDx Nuremberg)

---

## 1. Real-World Problem Statement & Solution

### The Problem in Our Faculty
At the **Faculty of Information Technology, University of Moratuwa (FIT UoM)**, student societies (such as INTECS, IEEE Student Branch, and Career Guidance Unit) organize multiple flagship events each year—such as 24-hour hackathons (FIT Future Sparks), coding battles (MoraXtreme), and career summits.

In traditional practice:
- Event notices and call-outs for volunteer sub-teams are scattered across informal WhatsApp batch groups and physical noticeboards.
- Students miss application deadlines or don't know who is coordinating each team.
- Organizers struggle to track volunteer applicants across sub-teams (Finance, Design, Logistics, Technical, etc.) and resort to messy, fragmented Google Forms and spreadsheets.
- Once volunteers are selected, assigning deliverables and tracking operational tasks (`To do` → `In progress` → `Done`) is chaotic.

### The Solution: CampusGather
**CampusGather** is a purpose-built, collaborative event operations web platform that provides:
1. **Centralized Calendar:** A searchable, filterable directory of all IT faculty events.
2. **Organizing Sub-Teams (Sections):** Every event can spawn specialized functional sections (Finance, Design, Logistics, Technical, Programme, Sponsorship, etc.).
3. **Student Application Workflow:** Students sign in with their university student ID to review section responsibilities, required skills, and apply directly.
4. **Organizer Management Command Center:** Organizers have full CRUD capabilities to manage their event, edit sections, approve or reject student applicants with one click, and assign tasks.
5. **Interactive Kanban Task Boards:** Operational deliverables are tracked visually across `To do`, `In progress`, and `Done` states.
6. **Student Portal (My Dashboard):** Students track all their applied teams and manage their assigned personal tasks in real time.

---

## 2. Complete Page Keymap & Information Architecture

| Page | File | Purpose | CRUD Operations |
|---|---|---|---|
| **Home / Calendar** | `index.php` | Faculty hero, live search by keyword, category filtering, joining status filter, timeline toggle (upcoming/past), and responsive event cards. | **Read** (Events & Section Counts) |
| **Event Details** | `event.php` | Comprehensive event details, countdown to application deadline, organizer contacts, venue, and grid of all 8 organizing sections with capacity bars. | **Read** (Event, Sections) |
| **Section Details** | `section.php` | Deep dive into a single organizing section: team leader info, responsibilities, useful skills, approved student roster, 3-column Kanban tasks board, resources, and "Apply to Join" form. | **Read** (Section, Tasks, Members), **Create** (Application) |
| **Create Event** | `create-event.php` | Organizer event publisher with interactive client-side section builder (1-click template selection or custom section creation). | **Create** (Event, Sections, Initial Tasks) |
| **Manage Event** | `manage-event.php` | Organizer command center with 4 tabs: Review & Approve Applications, Manage Sections (Open/Close), Assign & Track Tasks, Edit Core Event Details. | **Read, Update, Delete** (Event, Sections, Applications, Tasks) |
| **My Dashboard** | `my-dashboard.php` | Student personal portal: my submitted applications (pending/approved), my active teams, my personal tasks with one-click progress toggle, and events I created. | **Read, Update** (My tasks) |
| **Sign In** | `login.php` | Secure student and organizer sign-in with quick-fill demo credentials box. | **Read** (Authentication Session) |
| **Student Registration** | `register.php` | Student signup with Moratuwa Student ID validation, department selection, and password hashing. | **Create** (User Account) |
| **Logout** | `logout.php` | Secure session termination with CSRF token check. | Session Destroy |
| **Delete Handler** | `delete.php` | Unified, protected deletion endpoint for events, sections, tasks, and applications. | **Delete** (Entities) |

---

## 3. The 8 Organizing Sections (Faculty Standards)

Every major event at the Faculty of Information Technology relies on specialized student sub-teams. CampusGather provides pre-built templates for all eight:

1. **Finance (💳):** Plans the event budget, tracks expenditures, verifies vendor quotations and receipts, manages cash floats on event day, and balances final audit statements.
2. **Design (🎨):** Builds the visual brand identity, social media teaser carousels (Instagram, LinkedIn), stage backdrops, participant ID cards, and official winner certificates.
3. **Publicity & Media (📣):** Schedules announcements across batch groups, drafts promotional copy, records promotional video reels, and manages live event-day photography.
4. **Logistics (📦):** Reserves faculty auditoriums and computer labs, coordinates table layouts for hackathon teams, inspects power extensions, and verifies audio/mic setups.
5. **Technical (💻):** Liaises with the Network Center for dedicated Wi-Fi bandwidth, deploys code submission and judging servers (GitHub / DOMjudge), and handles AV projector feeds.
6. **Programme (📋):** Curates the event schedule and minute-by-minute agenda, coordinates guest speakers and judges, conducts rehearsals, and briefs Master of Ceremonies (MCs).
7. **Sponsorship (🤝):** Prepares corporate sponsorship proposals, pitches to top tech companies, manages sponsor tier deliverables, and hosts sponsor delegates.
8. **Registration & Hospitality (🎟️):** Builds attendee check-in lists, issues wristbands and welcome kits, coordinates participant meals, tea breaks, and VIP hospitality.

---

## 4. How the Frontend Connects to the Backend (Beginner's Guide)

If you are new to backend development, here is how the connection works step-by-step:

```
[Student's Browser]
        │
        │ 1. User submits an HTML Form (e.g. "Apply to Join Section")
        ▼
   [HTTP POST]  ───(Sends form inputs + CSRF Security Token)───┐
                                                                ▼
                                                        [Apache Web Server]
                                                                │
                                                                │ 2. Hands request to PHP
                                                                ▼
                                                        [PHP: section.php]
                                                                │
                                                                │ 3. Validates session ($_SESSION)
                                                                │ 4. Prepares SQL Query via PDO
                                                                ▼
                                                        [MySQL Database]
                                                                │
                                                                │ 5. Executes INSERT query
                                                                │    Stores application record
                                                                ▼
                                                        [PHP: section.php]
                                                                │
                                                                │ 6. Sets flash message
                                                                │ 7. Redirects or renders HTML
                                                                ▼
                                                    [Student's Browser Screen]
                                                    (Displays updated status badge)
```

### Key Technical Concepts:
- **`$_POST` and `$_GET`**: When an HTML form is submitted with `method="post"`, PHP receives the input values inside the `$_POST` superglobal array. When a URL contains query parameters (e.g., `event.php?id=1`), PHP reads them through `$_GET['id']`.
- **PHP Data Objects (PDO)**: Located in `includes/app.php`, PDO connects PHP to the MySQL database server. All queries use **Prepared Statements** (`$stmt->prepare(...)` and `$stmt->execute(...)`), ensuring user inputs are properly sanitized and 100% immune to SQL Injection attacks.
- **PHP Sessions (`$_SESSION`)**: When a student signs in, PHP creates a secure cookie on the student's browser and remembers their `user_id`, `name`, and `role` across page navigation without needing to re-login.
- **CSRF Tokens (`token()` & `csrf()`)**: To protect users from cross-site request forgery, every modifying form includes a hidden security token (`<input type="hidden" name="csrf" value="...">`) verified on the server before database changes are executed.

---

## 5. Minimum Functional Requirement: Complete CRUD Matrix

This application fulfills and exceeds the Assignment 02 CRUD requirement across multiple core entities:

| Entity | Create (C) | Read (R) | Update (U) | Delete (D) |
|---|---|---|---|---|
| **Events** | `create-event.php`: Organizer creates event with title, date, venue, society, and deadline. | `index.php` (calendar) & `event.php` (details view). | `manage-event.php` (Tab 4): Edit event venue, date, description, or status. | `delete.php`: Permanently delete event (cascades to all sections). |
| **Organizing Sections** | `create-event.php` or `manage-event.php` (Tab 2): Add section manually or via 8 templates. | `event.php` (sections grid) & `section.php` (detail view). | `manage-event.php` (Tab 2): Change section capacity, leader, or toggle Open/Closed. | `delete.php`: Delete a section and its tasks. |
| **Student Applications** | `section.php`: Student submits application note and skills. | `manage-event.php` (Tab 1): Organizer reviews all applicants. `my-dashboard.php`: Student tracks status. | `manage-event.php` (Tab 1): Organizer clicks **Approve** or **Reject**. | `delete.php`: Delete application record. |
| **Section Tasks** | `manage-event.php` (Tab 3): Create task, set deadline, and assign to student. | `section.php` (Kanban board) & `my-dashboard.php` (My tasks). | `section.php` & `my-dashboard.php`: Move task (`To do` → `In progress` → `Done`). | `delete.php`: Delete deliverable task. |
| **Student Accounts** | `register.php`: Register new account. | `login.php` & `my-dashboard.php`: Fetch user profile. | Session profile management. | CLI database reset. |

---

## 6. Local XAMPP Setup Instructions

### Step 1: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 2: Place Code in `htdocs`
Ensure this `campus-gather` project folder is located inside your server's `htdocs` directory:
- **Windows:** `C:\xampp\htdocs\campus-gather`
- **macOS:** `/Applications/XAMPP/xamppfiles/htdocs/campus-gather`

*(Confirm that `index.php` is directly inside `campus-gather`, not nested in an extra subfolder).*

### Step 3: Import the Database
1. Open your web browser and go to: **`http://localhost/phpmyadmin/`**
2. Click on the **Import** tab in the top navigation bar.
3. Click **Choose File** and select `database.sql` from the `campus-gather` folder.
4. Click **Import** (or **Go**) at the bottom.
5. The `campus_gather` database will be created, with all 5 tables and the rich pre-populated **FIT Future Sparks 2026** event with 8 organizing sections, tasks, and sample users.

### Step 4: Configure Database Connection
Open `config.php` in your code editor. The default local settings are:
```php
<?php
return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'campus_gather',
    'username' => 'root',
    'password' => '' // Leave empty for default XAMPP
];
```
*(If your MySQL server has a root password, enter it in `password`).*

### Step 5: Open in Your Browser
Visit: **`http://localhost/campus-gather/`**

---

## 7. Demo Evaluation Accounts

Two pre-configured accounts are seeded in the database for instant testing and grading:

| Role | Email | Password | Student ID | Notes |
|---|---|---|---|---|
| **Event Organizer / Admin** | `organizer@mora.ac.lk` | `Password123!` | `210001A` | Created the "FIT Future Sparks 2026" event. Has full access to **Manage Event** (Approve applicants, edit sections, assign tasks). |
| **Student Volunteer** | `student@mora.ac.lk` | `Password123!` | `220045B` | Approved member of the **Design** section. Has assigned tasks on **My Dashboard** ready to toggle. |

*(You can also click **Register** to create any new student account at any time).*

---

## 8. Video Demonstration Presentation Script (8–10 Minutes)

Use this structured script to record your group demonstration video for Assignment 02:

| Timestamp | Topic | Presenter | What to Demonstrate on Screen |
|---|---|---|---|
| **0:00 – 1:30** | **Project Overview & Real-World Problem** | Member 1 (Leader) | Introduce group members, Faculty of Information Technology context, problem of fragmented notices, and how CampusGather solves it. |
| **1:30 – 3:00** | **Calendar, Search & Filters (Read)** | Member 2 | Open `index.php`. Demonstrate live keyword search (e.g. "Hackathon"), category filtering, and status toggle. Highlight clean White & Royal Blue TEDx-style design. |
| **3:00 – 4:30** | **Event Details & Organizing Sections** | Member 3 | Click on "FIT Future Sparks 2026". Show the 8 sections (Finance, Design, Logistics, etc.), leader info, and capacity bars. Open the "Design" section to view the 3-column Kanban tasks board (`To do`, `In progress`, `Done`). |
| **4:30 – 6:00** | **Student Flow: Register & Apply (Create)** | Member 4 | Open an Incognito window or register a new student account (`220099K`). Go to an open section (e.g. Publicity & Media), type a statement, and submit application. Show "Application Under Review" status. |
| **6:00 – 7:45** | **Organizer Flow: Full Management (CRUD)** | Member 1 & 5 | Log in as `organizer@mora.ac.lk`. Open **Manage Event**: <br>1. *Approve* the new student application.<br>2. *Create* a new task and assign it to the student.<br>3. *Update* a section requirement or venue.<br>4. *Delete* a test task or section. |
| **7:45 – 9:00** | **Student Portal: My Dashboard & Task Toggle** | Member 2 & 4 | Switch back to the student account. Open **My Dashboard**. Show that the application is now marked **Approved**, and the assigned task appears. Click **Start Task** and **Mark Done** to demonstrate interactive progress. |
| **9:00 – 10:00** | **Architecture, Tech Stack & Member Contributions** | Member 5 | Briefly show VS Code files (`database.sql`, `app.php`, PDO prepared statements). Mention how frontend connects to backend without frameworks. Summarize individual member contributions. |

---

## 9. Deliverables Checklist for Submission

- [x] Project Source Code (clean PHP, CSS, JS, SQL files in repository)
- [x] Pre-populated Database Schema with seed data (`database.sql`)
- [x] Individual Contributions Text File (`docs/contributions.txt`)
- [x] Project Documentation & Architecture (`README.md`)
- [x] 8–10 Minute Video Demonstration Plan & Script
