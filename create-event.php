<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = require_login('Please sign in to launch and organize a university event.');

$templates = standard_section_templates();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $society = trim((string)($_POST['society'] ?? ''));
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $venue = trim((string)($_POST['venue'] ?? ''));
    $organiserName = trim((string)($_POST['organiser_name'] ?? ''));
    $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
    $joinDeadline = trim((string)($_POST['join_deadline'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'Open for joining'));
    $description = trim((string)($_POST['description'] ?? ''));
    $sectionsData = $_POST['sections'] ?? [];

    // Validations
    if ($title === '') {
        $errors[] = 'Event title is required.';
    }
    if (!in_array($category, event_categories(), true)) {
        $errors[] = 'Please select a valid event category.';
    }
    if ($society === '') {
        $errors[] = 'Organizing society or student group is required.';
    }
    if ($startsAt === '') {
        $errors[] = 'Event date and time is required.';
    }
    if ($venue === '') {
        $errors[] = 'Event venue is required.';
    }
    if ($organiserName === '') {
        $errors[] = 'Main organiser name is required.';
    }
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid contact email address is required.';
    }
    if ($joinDeadline === '') {
        $errors[] = 'Section joining deadline is required.';
    }
    if ($description === '') {
        $errors[] = 'Event description is required.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $formattedStartsAt = date('Y-m-d H:i:s', strtotime($startsAt));
            $formattedDeadline = date('Y-m-d', strtotime($joinDeadline));

            $eventStmt = $db->prepare('
                INSERT INTO events (title, category, society, starts_at, venue, organiser_name, contact_email, contact_phone, join_deadline, status, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $eventStmt->execute([
                $title, $category, $society, $formattedStartsAt, $venue,
                $organiserName, $contactEmail, $contactPhone, $formattedDeadline,
                $status, $description, $user['id']
            ]);
            $eventId = (int)$db->lastInsertId();

            // Insert Sections
            if (is_array($sectionsData)) {
                $secStmt = $db->prepare('
                    INSERT INTO sections (event_id, name, description, leader_name, leader_email, responsibilities, useful_skills, member_limit, joining_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $taskStmt = $db->prepare('
                    INSERT INTO tasks (section_id, title, description, status)
                    VALUES (?, ?, ?, "todo")
                ');

                foreach ($sectionsData as $s) {
                    $secName = trim((string)($s['name'] ?? ''));
                    if ($secName === '') continue;

                    $secDesc = trim((string)($s['description'] ?? ''));
                    $secLeader = trim((string)($s['leader_name'] ?? $user['name']));
                    $secLeaderEmail = trim((string)($s['leader_email'] ?? ''));
                    $secResp = trim((string)($s['responsibilities'] ?? ''));
                    $secSkills = trim((string)($s['useful_skills'] ?? ''));
                    $secLimit = max(1, (int)($s['member_limit'] ?? 10));

                    $secStmt->execute([
                        $eventId, $secName, $secDesc, $secLeader, $secLeaderEmail,
                        $secResp, $secSkills, $secLimit, 'Open'
                    ]);
                    $sectionId = (int)$db->lastInsertId();

                    // If it matches a standard template, pre-populate default tasks
                    if (isset($templates[$secName]['default_tasks'])) {
                        foreach ($templates[$secName]['default_tasks'] as $taskTitle) {
                            $taskStmt->execute([$sectionId, $taskTitle, 'Starter task for ' . $secName . ' section']);
                        }
                    }
                }
            }

            $db->commit();
            set_flash('Event "' . $title . '" and its organizing sections have been created successfully!', 'success');
            header('Location: event.php?id=' . $eventId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to create event: ' . $e->getMessage();
        }
    }
}

header_view('Create University Event', 'create');
?>

<div class="breadcrumb">
    <a href="index.php">← Events Calendar</a>
    <span>/</span>
    <span>Create Event</span>
</div>

<div class="form-shell">
    <div class="form-header">
        <span class="eyebrow">FACULTY EVENT PUBLISHING</span>
        <h1>Launch a New University Event</h1>
        <p>Set up event details and configure student organizing sections (Finance, Design, Logistics, Technical, and more).</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠</span>
            <div>
                <strong>Please correct the following errors:</strong>
                <ul style="margin:6px 0 0 16px; padding:0;">
                    <?php foreach ($errors as $err): ?>
                        <li><?=h($err)?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="create-event.php" class="form-card">
        <input type="hidden" name="csrf" value="<?=token()?>">

        <h2 style="font-size:18px; font-weight:800; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin:0 0 20px; color:#0f172a;">
            1. Event Information
        </h2>

        <div class="form-group">
            <label for="title">Event Name / Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" value="<?=h($_POST['title'] ?? '')?>" placeholder="e.g. FIT Future Sparks 2026: Annual Tech Summit & Hackathon" required maxlength="160">
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="category">Category <span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach (event_categories() as $cat): ?>
                        <option value="<?=h($cat)?>" <?=($_POST['category'] ?? '') === $cat ? 'selected' : ''?>><?=h($cat)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="society">Organizing Society / Student Group <span class="required">*</span></label>
                <input type="text" id="society" name="society" value="<?=h($_POST['society'] ?? 'INTECS (IT Society, UoM)')?>" placeholder="e.g. INTECS, IEEE Student Branch, MoraSpirit" required maxlength="120">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="starts_at">Event Date & Time <span class="required">*</span></label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="<?=h($_POST['starts_at'] ?? '')?>" required>
                <span class="help-text">Sri Lanka Standard Time (UTC+05:30)</span>
            </div>

            <div class="form-group">
                <label for="venue">Event Venue / Location <span class="required">*</span></label>
                <input type="text" id="venue" name="venue" value="<?=h($_POST['venue'] ?? 'Faculty of IT Complex, University of Moratuwa')?>" placeholder="e.g. Faculty Auditorium, Computer Lab 2" required maxlength="160">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="organiser_name">Main Organiser / Lead Coordinator <span class="required">*</span></label>
                <input type="text" id="organiser_name" name="organiser_name" value="<?=h($_POST['organiser_name'] ?? $user['name'])?>" required maxlength="120">
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email <span class="required">*</span></label>
                <input type="email" id="contact_email" name="contact_email" value="<?=h($_POST['contact_email'] ?? $user['email'])?>" required maxlength="190">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="contact_phone">Contact Phone (Optional)</label>
                <input type="tel" id="contact_phone" name="contact_phone" value="<?=h($_POST['contact_phone'] ?? '')?>" placeholder="e.g. +94 77 123 4567" maxlength="50">
            </div>

            <div class="form-group">
                <label for="join_deadline">Section Joining Deadline <span class="required">*</span></label>
                <input type="date" id="join_deadline" name="join_deadline" value="<?=h($_POST['join_deadline'] ?? '')?>" required>
                <span class="help-text">Last date for students to apply for organizing sections</span>
            </div>
        </div>

        <div class="form-group">
            <label for="status">Event Initial Status</label>
            <select id="status" name="status">
                <?php foreach (event_statuses() as $st): ?>
                    <option value="<?=h($st)?>" <?=($_POST['status'] ?? 'Open for joining') === $st ? 'selected' : ''?>><?=h($st)?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Event Detailed Description <span class="required">*</span></label>
            <textarea id="description" name="description" rows="4" placeholder="Provide background, event objectives, expected audience, and what students will experience..." required><?=h($_POST['description'] ?? '')?></textarea>
        </div>

        <!-- 2. Dynamic Organizing Sections Builder -->
        <div style="margin-top:36px; border-top:2px solid #e2e8f0; padding-top:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <div>
                    <h2 style="font-size:18px; font-weight:800; margin:0; color:#0f172a;">
                        2. Organizing Sections & Teams
                    </h2>
                    <small style="color:#64748b;">Click template buttons below to quickly add standard faculty sub-teams, or add a custom section.</small>
                </div>
                <button type="button" id="btn-add-custom-section" class="btn-secondary" style="font-size:12px; padding:6px 12px;">
                    + Add Custom Section
                </button>
            </div>

            <!-- Template Quick Add Pills -->
            <div class="template-quick-buttons">
                <?php foreach ($templates as $key => $tpl): ?>
                    <button type="button" class="btn-template-pill" data-name="<?=h($key)?>">
                        <span><?=$tpl['icon']?></span>
                        <span>+ <?=h($key)?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Dynamic Container where cards appear -->
            <div id="sections-container" class="section-builder-box">
                <p style="color:#64748b; font-size:13px; margin:0 0 12px; text-align:center;">
                    Click any section button above to add teams to your event.
                </p>
            </div>
        </div>

        <div style="margin-top:32px; border-top:1px solid #e2e8f0; padding-top:20px; display:flex; gap:16px; align-items:center;">
            <button type="submit" class="btn-primary" style="font-size:15px; padding:12px 28px;">
                Publish Event & Launch Sections →
            </button>
            <a href="index.php" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<!-- Pass PHP template data to JavaScript -->
<script>
    window.SECTION_TEMPLATES = <?=json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
</script>

<?php footer_view(); ?>
