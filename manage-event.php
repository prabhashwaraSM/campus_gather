<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = require_login('Please sign in to manage this event.');

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
    set_flash('Invalid event ID.', 'error');
    header('Location: index.php');
    exit;
}

// Fetch event
$stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    set_flash('Event not found.', 'error');
    header('Location: index.php');
    exit;
}

// Authorization check: must be owner or admin
if (!can_manage_event($event)) {
    http_response_code(403);
    set_flash('You do not have permission to manage this event.', 'error');
    header('Location: event.php?id=' . $eventId);
    exit;
}

$templates = standard_section_templates();
$errors = [];

// ==============================================================================
// POST HANDLERS FOR MANAGEMENT ACTIONS
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $action = trim((string)($_POST['action'] ?? ''));

    // 1. UPDATE EVENT DETAILS
    if ($action === 'update_event') {
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

        if ($title === '' || $startsAt === '' || $venue === '') {
            $errors[] = 'Event title, date, and venue cannot be empty.';
        } else {
            $formattedStartsAt = date('Y-m-d H:i:s', strtotime($startsAt));
            $formattedDeadline = date('Y-m-d', strtotime($joinDeadline));

            $upd = $db->prepare('
                UPDATE events 
                SET title = ?, category = ?, society = ?, starts_at = ?, venue = ?, organiser_name = ?,
                    contact_email = ?, contact_phone = ?, join_deadline = ?, status = ?, description = ?
                WHERE id = ?
            ');
            $upd->execute([
                $title, $category, $society, $formattedStartsAt, $venue, $organiserName,
                $contactEmail, $contactPhone, $formattedDeadline, $status, $description, $eventId
            ]);
            set_flash('Event details updated successfully.');
            header('Location: manage-event.php?id=' . $eventId . '#tab-details');
            exit;
        }
    }

    // 2. ADD A NEW SECTION
    elseif ($action === 'add_section') {
        $secName = trim((string)($_POST['sec_name'] ?? ''));
        $secDesc = trim((string)($_POST['sec_description'] ?? ''));
        $secLeader = trim((string)($_POST['sec_leader_name'] ?? ''));
        $secLeaderEmail = trim((string)($_POST['sec_leader_email'] ?? ''));
        $secResp = trim((string)($_POST['sec_responsibilities'] ?? ''));
        $secSkills = trim((string)($_POST['sec_useful_skills'] ?? ''));
        $secLimit = max(1, (int)($_POST['sec_member_limit'] ?? 10));

        if ($secName === '' || $secLeader === '') {
            $errors[] = 'Section name and leader name are required.';
        } else {
            $ins = $db->prepare('
                INSERT INTO sections (event_id, name, description, leader_name, leader_email, responsibilities, useful_skills, member_limit, joining_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Open")
            ');
            $ins->execute([
                $eventId, $secName, $secDesc, $secLeader, $secLeaderEmail, $secResp, $secSkills, $secLimit
            ]);
            set_flash('Section "' . $secName . '" added to event.');
            header('Location: manage-event.php?id=' . $eventId . '#tab-sections');
            exit;
        }
    }

    // 3. UPDATE EXISTING SECTION
    elseif ($action === 'update_section') {
        $secId = (int)($_POST['section_id'] ?? 0);
        $secName = trim((string)($_POST['sec_name'] ?? ''));
        $secDesc = trim((string)($_POST['sec_description'] ?? ''));
        $secLeader = trim((string)($_POST['sec_leader_name'] ?? ''));
        $secLeaderEmail = trim((string)($_POST['sec_leader_email'] ?? ''));
        $secResp = trim((string)($_POST['sec_responsibilities'] ?? ''));
        $secSkills = trim((string)($_POST['sec_useful_skills'] ?? ''));
        $secLimit = max(1, (int)($_POST['sec_member_limit'] ?? 10));
        $secStatus = in_array($_POST['sec_joining_status'] ?? '', ['Open', 'Closed'], true) ? $_POST['sec_joining_status'] : 'Open';
        $secResources = trim((string)($_POST['sec_resources'] ?? ''));

        $upd = $db->prepare('
            UPDATE sections 
            SET name = ?, description = ?, leader_name = ?, leader_email = ?, responsibilities = ?,
                useful_skills = ?, member_limit = ?, joining_status = ?, resources = ?
            WHERE id = ? AND event_id = ?
        ');
        $upd->execute([
            $secName, $secDesc, $secLeader, $secLeaderEmail, $secResp,
            $secSkills, $secLimit, $secStatus, $secResources, $secId, $eventId
        ]);
        set_flash('Section "' . $secName . '" updated successfully.');
        header('Location: manage-event.php?id=' . $eventId . '#tab-sections');
        exit;
    }

    // 4. APPROVE / REJECT APPLICATION
    elseif ($action === 'decide_application') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';

        // Ensure application belongs to this event
        $check = $db->prepare('
            SELECT sm.id, sm.section_id, s.name AS section_name, u.name AS user_name
            FROM section_members sm
            JOIN sections s ON sm.section_id = s.id
            JOIN users u ON sm.user_id = u.id
            WHERE sm.id = ? AND s.event_id = ?
        ');
        $check->execute([$appId, $eventId]);
        $app = $check->fetch();

        if ($app) {
            $upd = $db->prepare('UPDATE section_members SET status = ?, decided_at = NOW() WHERE id = ?');
            $upd->execute([$decision, $appId]);
            set_flash('Application for ' . $app['user_name'] . ' was ' . $decision . ' for ' . $app['section_name'] . '.');
        }
        header('Location: manage-event.php?id=' . $eventId . '#tab-applications');
        exit;
    }

    // 5. CREATE TASK
    elseif ($action === 'create_task') {
        $secId = (int)($_POST['task_section_id'] ?? 0);
        $taskTitle = trim((string)($_POST['task_title'] ?? ''));
        $taskDesc = trim((string)($_POST['task_description'] ?? ''));
        $assigneeId = !empty($_POST['task_assignee_id']) ? (int)$_POST['task_assignee_id'] : null;
        $taskDeadline = !empty($_POST['task_deadline']) ? date('Y-m-d', strtotime($_POST['task_deadline'])) : null;
        $taskStatus = in_array($_POST['task_status'] ?? '', ['todo', 'in_progress', 'done'], true) ? $_POST['task_status'] : 'todo';

        if ($taskTitle === '' || $secId <= 0) {
            $errors[] = 'Task title and section are required.';
        } else {
            $ins = $db->prepare('
                INSERT INTO tasks (section_id, title, description, assigned_to_user_id, deadline, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([$secId, $taskTitle, $taskDesc, $assigneeId, $taskDeadline, $taskStatus]);
            set_flash('New task "' . $taskTitle . '" created.');
            header('Location: manage-event.php?id=' . $eventId . '#tab-tasks');
            exit;
        }
    }
}

// Fetch fresh sections list
$secStmt = $db->prepare('
    SELECT s.*, 
           COUNT(DISTINCT CASE WHEN sm.status = "approved" THEN sm.id END) AS approved_count,
           COUNT(DISTINCT sm.id) AS total_apps,
           COUNT(DISTINCT t.id) AS task_count
    FROM sections s
    LEFT JOIN section_members sm ON sm.section_id = s.id
    LEFT JOIN tasks t ON t.section_id = s.id
    WHERE s.event_id = ?
    GROUP BY s.id
    ORDER BY s.id ASC
');
$secStmt->execute([$eventId]);
$sections = $secStmt->fetchAll();

// Fetch all applications
$appsStmt = $db->prepare('
    SELECT sm.*, s.name AS section_name, u.name AS user_name, u.student_id, u.email AS user_email, u.department
    FROM section_members sm
    JOIN sections s ON sm.section_id = s.id
    JOIN users u ON sm.user_id = u.id
    WHERE s.event_id = ?
    ORDER BY sm.status = "pending" DESC, sm.applied_at DESC
');
$appsStmt->execute([$eventId]);
$applications = $appsStmt->fetchAll();

// Fetch all approved members for task assignment dropdown
$approvedMembersStmt = $db->prepare('
    SELECT DISTINCT u.id, u.name, u.student_id, s.name AS section_name
    FROM section_members sm
    JOIN sections s ON sm.section_id = s.id
    JOIN users u ON sm.user_id = u.id
    WHERE s.event_id = ? AND sm.status = "approved"
    ORDER BY u.name ASC
');
$approvedMembersStmt->execute([$eventId]);
$approvedMembers = $approvedMembersStmt->fetchAll();

// Fetch all tasks for this event
$tasksStmt = $db->prepare('
    SELECT t.*, s.name AS section_name, u.name AS assignee_name
    FROM tasks t
    JOIN sections s ON t.section_id = s.id
    LEFT JOIN users u ON t.assigned_to_user_id = u.id
    WHERE s.event_id = ?
    ORDER BY t.created_at DESC
');
$tasksStmt->execute([$eventId]);
$allTasks = $tasksStmt->fetchAll();

$pendingCount = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));

header_view('Manage ' . $event['title'], 'events');
?>

<div class="breadcrumb">
    <a href="index.php">← Events Calendar</a>
    <span>/</span>
    <a href="event.php?id=<?=$eventId?>"><?=h($event['title'])?></a>
    <span>/</span>
    <span>Organizer Management</span>
</div>

<div class="hero-top" style="margin-bottom:20px;">
    <div>
        <span class="eyebrow">ORGANIZER DASHBOARD</span>
        <h1 style="font-size:28px; font-weight:800; margin:6px 0 4px; color:#0f172a;">
            Manage Event: <?=h($event['title'])?>
        </h1>
        <p style="color:#64748b; font-size:14px; margin:0;">
            Full CRUD Control · Edit event details, manage sections, approve student applications, and assign tasks.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="event.php?id=<?=$eventId?>" class="btn-secondary">View Public Page ↗</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <span class="alert-icon">⚠</span>
        <div>
            <?php foreach ($errors as $e): ?>
                <div><?=h($e)?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="tabs-container">
    <!-- Tabs Navigation -->
    <nav class="tabs-nav" aria-label="Management sections">
        <button type="button" class="tab-btn active" data-tab="applications">
            <span>👥 Student Applications</span>
            <?php if ($pendingCount > 0): ?>
                <span style="background:#dc2626; color:#ffffff; font-size:11px; padding:2px 7px; border-radius:999px; font-weight:800;">
                    <?=$pendingCount?> Pending
                </span>
            <?php endif; ?>
        </button>
        <button type="button" class="tab-btn" data-tab="sections">
            <span>✦ Organizing Sections (<?=count($sections)?>)</span>
        </button>
        <button type="button" class="tab-btn" data-tab="tasks">
            <span>📋 Section Tasks (<?=count($allTasks)?>)</span>
        </button>
        <button type="button" class="tab-btn" data-tab="details">
            <span>⚙ Event Details & Settings</span>
        </button>
    </nav>

    <!-- TAB 1: STUDENT APPLICATIONS (READ, UPDATE, DELETE) -->
    <div class="tab-pane active" id="tab-applications">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h2 style="font-size:18px; font-weight:800; margin:0; color:#0f172a;">Student Join Applications</h2>
                <small style="color:#64748b;">Review undergraduate applicants and assign them to approved section rosters.</small>
            </div>
        </div>

        <?php if (empty($applications)): ?>
            <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:36px; text-align:center; color:#64748b;">
                No students have applied to join organizing sections for this event yet.
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Section Applied</th>
                            <th>Statement / Skills</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong><?=h($app['user_name'])?></strong><br>
                                    <small style="font-family:var(--font-mono); color:#64748b;"><?=h($app['student_id'])?> · <?=h($app['department'])?></small><br>
                                    <small><a href="mailto:<?=h($app['user_email'])?>"><?=h($app['user_email'])?></a></small>
                                </td>
                                <td>
                                    <span class="sections-pill">✦ <?=h($app['section_name'])?></span>
                                </td>
                                <td style="max-width:240px; font-size:12px; color:#475569;">
                                    <?=h($app['applicant_note'] ?: 'No statement provided.')?>
                                </td>
                                <td style="font-size:12px; color:#64748b; white-space:nowrap;">
                                    <?=h((new DateTime($app['applied_at']))->format('M d, Y'))?>
                                </td>
                                <td>
                                    <?php if ($app['status'] === 'approved'): ?>
                                        <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;">✓ Approved</span>
                                    <?php elseif ($app['status'] === 'pending'): ?>
                                        <span class="badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">⏳ Pending</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca;">✕ Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <div style="display:flex; gap:6px;">
                                        <?php if ($app['status'] !== 'approved'): ?>
                                            <form method="post" action="manage-event.php?id=<?=$eventId?>" style="margin:0;">
                                                <input type="hidden" name="csrf" value="<?=token()?>">
                                                <input type="hidden" name="action" value="decide_application">
                                                <input type="hidden" name="application_id" value="<?=$app['id']?>">
                                                <input type="hidden" name="decision" value="approve">
                                                <button type="submit" class="btn-primary" style="padding:4px 10px; font-size:11px; background:#059669; border-color:#059669;">
                                                    Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($app['status'] !== 'rejected'): ?>
                                            <form method="post" action="manage-event.php?id=<?=$eventId?>" style="margin:0;">
                                                <input type="hidden" name="csrf" value="<?=token()?>">
                                                <input type="hidden" name="action" value="decide_application">
                                                <input type="hidden" name="application_id" value="<?=$app['id']?>">
                                                <input type="hidden" name="decision" value="reject">
                                                <button type="submit" class="btn-secondary" style="padding:4px 10px; font-size:11px; color:#dc2626;">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" action="delete.php" data-confirm="Remove this student application record completely?" style="margin:0;">
                                            <input type="hidden" name="csrf" value="<?=token()?>">
                                            <input type="hidden" name="type" value="application">
                                            <input type="hidden" name="id" value="<?=$app['id']?>">
                                            <input type="hidden" name="event_id" value="<?=$eventId?>">
                                            <button type="submit" class="btn-danger" style="padding:4px 8px; font-size:11px;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: SECTIONS MANAGEMENT (CREATE, READ, UPDATE, DELETE) -->
    <div class="tab-pane" id="tab-sections">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:18px; font-weight:800; margin:0; color:#0f172a;">Active Organizing Sections</h2>
                <small style="color:#64748b;">Add, edit requirements, change member capacity, or close joining for any section.</small>
            </div>
        </div>

        <!-- Existing Sections List -->
        <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:36px;">
            <?php foreach ($sections as $s): ?>
                <div class="form-card" style="padding:20px; border-left:4px solid #1d4ed8;">
                    <form method="post" action="manage-event.php?id=<?=$eventId?>">
                        <input type="hidden" name="csrf" value="<?=token()?>">
                        <input type="hidden" name="action" value="update_section">
                        <input type="hidden" name="section_id" value="<?=$s['id']?>">

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                            <h3 style="margin:0; font-size:17px; font-weight:800; color:#1d4ed8;">
                                ✦ Section: <?=h($s['name'])?> 
                                <span style="font-size:12px; font-weight:500; color:#64748b;">(<?=$s['approved_count']?> approved / <?=$s['member_limit']?> max)</span>
                            </h3>

                            <div style="display:flex; gap:8px;">
                                <a href="section.php?id=<?=$s['id']?>" target="_blank" class="btn-secondary" style="font-size:11px; padding:4px 10px;">
                                    View Section Page ↗
                                </a>
                                <button type="submit" class="btn-primary" style="font-size:11px; padding:4px 12px;">
                                    Save Changes
                                </button>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Section Name</label>
                                <input type="text" name="sec_name" value="<?=h($s['name'])?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Joining Status</label>
                                <select name="sec_joining_status">
                                    <option value="Open" <?=$s['joining_status'] === 'Open' ? 'selected' : ''?>>Open for joining</option>
                                    <option value="Closed" <?=$s['joining_status'] === 'Closed' ? 'selected' : ''?>>Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Lead Coordinator Name</label>
                                <input type="text" name="sec_leader_name" value="<?=h($s['leader_name'])?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Lead Email</label>
                                <input type="email" name="sec_leader_email" value="<?=h($s['leader_email'] ?? '')?>">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label>What the team does (Description)</label>
                            <textarea name="sec_description" rows="2" required><?=h($s['description'])?></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Main Responsibilities</label>
                            <textarea name="sec_responsibilities" rows="3" required><?=h($s['responsibilities'])?></textarea>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Useful Skills / Prerequisites</label>
                                <input type="text" name="sec_useful_skills" value="<?=h($s['useful_skills'] ?? '')?>">
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>Member Limit (Capacity)</label>
                                <input type="number" name="sec_member_limit" value="<?=$s['member_limit']?>" min="1" max="50" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Resources & Shared Links (Google Drive / Docs / GitHub)</label>
                            <textarea name="sec_resources" rows="2" placeholder="https://..."><?=h($s['resources'] ?? '')?></textarea>
                        </div>
                    </form>

                    <!-- Delete Section Form -->
                    <form method="post" action="delete.php" data-confirm="Permanently delete the '<?=h($s['name'])?>' section along with all its tasks and member records?" style="margin-top:8px; text-align:right;">
                        <input type="hidden" name="csrf" value="<?=token()?>">
                        <input type="hidden" name="type" value="section">
                        <input type="hidden" name="id" value="<?=$s['id']?>">
                        <input type="hidden" name="event_id" value="<?=$eventId?>">
                        <button type="submit" class="btn-link" style="color:#dc2626; font-size:12px;">
                            🗑 Delete Section Permanently
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Add New Section Box -->
        <div class="form-card" style="border:2px dashed #bfdbfe; background:#f8fafc;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:800; color:#1d4ed8;">
                + Add Another Organizing Section
            </h3>
            <form method="post" action="manage-event.php?id=<?=$eventId?>">
                <input type="hidden" name="csrf" value="<?=token()?>">
                <input type="hidden" name="action" value="add_section">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Section Name <span class="required">*</span></label>
                        <input type="text" name="sec_name" placeholder="e.g. Sponsorship or Stage Crew" required>
                    </div>
                    <div class="form-group">
                        <label>Lead Coordinator Name <span class="required">*</span></label>
                        <input type="text" name="sec_leader_name" value="<?=h($user['name'])?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="sec_description" rows="2" placeholder="Explain the role of this organizing section..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Main Responsibilities <span class="required">*</span></label>
                    <textarea name="sec_responsibilities" rows="3" placeholder="List member duties..." required></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Useful Skills</label>
                        <input type="text" name="sec_useful_skills" placeholder="e.g. Communication, Excel, Beginners welcome">
                    </div>
                    <div class="form-group">
                        <label>Member Limit</label>
                        <input type="number" name="sec_member_limit" value="8" min="1" max="50" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    Create Section
                </button>
            </form>
        </div>
    </div>

    <!-- TAB 3: TASKS MANAGEMENT (CREATE, READ, UPDATE, DELETE) -->
    <div class="tab-pane" id="tab-tasks">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="font-size:18px; font-weight:800; margin:0; color:#0f172a;">Section Deliverables & Tasks</h2>
                <small style="color:#64748b;">Create tasks, assign them to approved students, set deadlines, and monitor completion.</small>
            </div>
        </div>

        <!-- Create Task Form -->
        <div class="form-card" style="margin-bottom:32px; background:#f8fafc; border:1px solid #bfdbfe;">
            <h3 style="margin:0 0 16px; font-size:16px; font-weight:800; color:#1d4ed8;">
                + Add & Assign New Task
            </h3>
            <form method="post" action="manage-event.php?id=<?=$eventId?>">
                <input type="hidden" name="csrf" value="<?=token()?>">
                <input type="hidden" name="action" value="create_task">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Target Organizing Section <span class="required">*</span></label>
                        <select name="task_section_id" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?=$s['id']?>"><?=h($s['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assignee (Approved Student Member)</label>
                        <select name="task_assignee_id">
                            <option value="">Unassigned (Open for team)</option>
                            <?php foreach ($approvedMembers as $m): ?>
                                <option value="<?=$m['id']?>"><?=h($m['name'])?> (<?=h($m['student_id'])?> - <?=h($m['section_name'])?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Task Title <span class="required">*</span></label>
                    <input type="text" name="task_title" placeholder="e.g. Design 1080x1080 social media launch poster" required maxlength="180">
                </div>

                <div class="form-group">
                    <label>Task Description & Instructions</label>
                    <textarea name="task_description" rows="2" placeholder="Details, specifications, links, or expectations..."></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Task Deadline</label>
                        <input type="date" name="task_deadline" value="<?=date('Y-m-d', strtotime('+7 days'))?>">
                    </div>
                    <div class="form-group">
                        <label>Initial Status</label>
                        <select name="task_status">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Completed</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    Create Task
                </button>
            </form>
        </div>

        <!-- Tasks Table -->
        <?php if (empty($allTasks)): ?>
            <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:32px; text-align:center; color:#64748b;">
                No tasks created yet for this event. Use the form above to add deliverables for organizing sections.
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Section</th>
                            <th>Assignee</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTasks as $t): ?>
                            <tr>
                                <td>
                                    <strong><?=h($t['title'])?></strong>
                                    <?php if (!empty($t['description'])): ?>
                                        <div style="font-size:12px; color:#64748b;"><?=h($t['description'])?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="sections-pill">✦ <?=h($t['section_name'])?></span>
                                </td>
                                <td>
                                    <?=h($t['assignee_name'] ?? 'Unassigned')?>
                                </td>
                                <td style="white-space:nowrap; font-size:12px;">
                                    <?=$t['deadline'] ? h((new DateTime($t['deadline']))->format('M d, Y')) : '—'?>
                                </td>
                                <td>
                                    <?=render_task_badge($t['status'])?>
                                </td>
                                <td>
                                    <form method="post" action="delete.php" data-confirm="Delete this task?" style="margin:0;">
                                        <input type="hidden" name="csrf" value="<?=token()?>">
                                        <input type="hidden" name="type" value="task">
                                        <input type="hidden" name="id" value="<?=$t['id']?>">
                                        <input type="hidden" name="event_id" value="<?=$eventId?>">
                                        <button type="submit" class="btn-danger" style="font-size:11px; padding:4px 8px;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 4: EVENT DETAILS & SETTINGS (UPDATE, DELETE EVENT) -->
    <div class="tab-pane" id="tab-details">
        <form method="post" action="manage-event.php?id=<?=$eventId?>" class="form-card" style="margin-bottom:32px;">
            <input type="hidden" name="csrf" value="<?=token()?>">
            <input type="hidden" name="action" value="update_event">

            <h2 style="font-size:18px; font-weight:800; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin:0 0 20px; color:#0f172a;">
                Edit Event Core Details
            </h2>

            <div class="form-group">
                <label for="title">Event Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" value="<?=h($event['title'])?>" required maxlength="160">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category" required>
                        <?php foreach (event_categories() as $cat): ?>
                            <option value="<?=h($cat)?>" <?=$event['category'] === $cat ? 'selected' : ''?>><?=h($cat)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="society">Organizing Society / Group <span class="required">*</span></label>
                    <input type="text" id="society" name="society" value="<?=h($event['society'])?>" required maxlength="120">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="starts_at">Event Date & Time <span class="required">*</span></label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="<?=date('Y-m-d\TH:i', strtotime($event['starts_at']))?>" required>
                </div>

                <div class="form-group">
                    <label for="venue">Venue <span class="required">*</span></label>
                    <input type="text" id="venue" name="venue" value="<?=h($event['venue'])?>" required maxlength="160">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="organiser_name">Main Organiser Name <span class="required">*</span></label>
                    <input type="text" id="organiser_name" name="organiser_name" value="<?=h($event['organiser_name'])?>" required maxlength="120">
                </div>

                <div class="form-group">
                    <label for="contact_email">Contact Email <span class="required">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" value="<?=h($event['contact_email'])?>" required maxlength="190">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="text" id="contact_phone" name="contact_phone" value="<?=h($event['contact_phone'] ?? '')?>">
                </div>

                <div class="form-group">
                    <label for="join_deadline">Section Joining Deadline <span class="required">*</span></label>
                    <input type="date" id="join_deadline" name="join_deadline" value="<?=h($event['join_deadline'])?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="status">Event Status</label>
                <select id="status" name="status">
                    <?php foreach (event_statuses() as $st): ?>
                        <option value="<?=h($st)?>" <?=$event['status'] === $st ? 'selected' : ''?>><?=h($st)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Detailed Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="5" required><?=h($event['description'])?></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; border-top:1px solid #e2e8f0; padding-top:16px;">
                <button type="submit" class="btn-primary">
                    Update Event Details
                </button>
            </div>
        </form>

        <!-- Danger Zone: Delete Entire Event -->
        <div class="form-card" style="border:1px solid #fecaca; background:#fef2f2;">
            <h3 style="color:#991b1b; margin:0 0 8px; font-size:16px; font-weight:800;">Danger Zone: Delete Event</h3>
            <p style="color:#b91c1c; font-size:13px; margin:0 0 16px;">
                Deleting this event will permanently erase all associated organizing sections, student applications, and task records.
            </p>
            <form method="post" action="delete.php" data-confirm="Are you absolutely sure you want to permanently delete this event? This action cannot be reversed.">
                <input type="hidden" name="csrf" value="<?=token()?>">
                <input type="hidden" name="type" value="event">
                <input type="hidden" name="id" value="<?=$eventId?>">
                <button type="submit" class="btn-danger">
                    Delete Entire Event Permanently
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Handle hash links for opening specific tabs
    document.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash;
        if (hash) {
            const tabName = hash.replace('#tab-', '');
            const targetBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
            if (targetBtn) {
                targetBtn.click();
            }
        }
    });
</script>

<?php footer_view(); ?>
