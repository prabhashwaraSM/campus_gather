<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$sectionId = (int)($_GET['id'] ?? 0);
if ($sectionId <= 0) {
    set_flash('Invalid section requested.', 'error');
    header('Location: index.php');
    exit;
}

// Fetch Section and Parent Event
$stmt = $db->prepare('
    SELECT s.*, 
           e.title AS event_title, e.starts_at, e.join_deadline, e.status AS event_status, e.created_by AS event_created_by,
           e.society, e.venue, e.organiser_name
    FROM sections s
    JOIN events e ON s.event_id = e.id
    WHERE s.id = ?
');
$stmt->execute([$sectionId]);
$section = $stmt->fetch();

if (!$section) {
    http_response_code(404);
    header_view('Section Not Found');
    echo '<div style="text-align:center; padding:60px 20px;"><h2>Section Not Found</h2><p>This organizing section does not exist or has been removed.</p><a href="index.php" class="btn-primary">Return to Calendar</a></div>';
    footer_view();
    exit;
}

$user = current_user();
$isManager = can_manage_event($section['event_created_by']);

// Handle Apply to Join form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_section') {
    csrf();
    $currentUser = require_login('Please sign in to submit a section application.');
    
    // Check if section is open
    $deadline = new DateTime($section['join_deadline']);
    $isPastDeadline = (new DateTime()) > $deadline;
    if ($section['joining_status'] !== 'Open' || $isPastDeadline) {
        set_flash('Joining for this section is currently closed.', 'error');
        header('Location: section.php?id=' . $sectionId);
        exit;
    }

    $note = trim((string)($_POST['applicant_note'] ?? ''));

    // Check existing application
    $checkStmt = $db->prepare('SELECT id, status FROM section_members WHERE section_id = ? AND user_id = ?');
    $checkStmt->execute([$sectionId, $currentUser['id']]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        set_flash('You have already submitted an application for this section (Status: ' . $existing['status'] . ').', 'error');
    } else {
        $ins = $db->prepare('INSERT INTO section_members (section_id, user_id, applicant_note, status) VALUES (?, ?, ?, "pending")');
        $ins->execute([$sectionId, $currentUser['id'], $note]);
        set_flash('Your application to join the ' . $section['name'] . ' team has been submitted for organizer review!', 'success');
    }

    header('Location: section.php?id=' . $sectionId);
    exit;
}

// Handle Task Status Quick Update (for approved members or manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_status') {
    csrf();
    $currentUser = require_login();
    $taskId = (int)($_POST['task_id'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));

    if (in_array($newStatus, ['todo', 'in_progress', 'done'], true)) {
        // Verify user is assigned OR manager OR approved member of this section
        $checkTask = $db->prepare('
            SELECT t.*, sm.status AS member_status 
            FROM tasks t
            LEFT JOIN section_members sm ON sm.section_id = t.section_id AND sm.user_id = ?
            WHERE t.id = ? AND t.section_id = ?
        ');
        $checkTask->execute([$currentUser['id'], $taskId, $sectionId]);
        $task = $checkTask->fetch();

        if ($task && ($isManager || $task['assigned_to_user_id'] == $currentUser['id'] || $task['member_status'] === 'approved')) {
            $upd = $db->prepare('UPDATE tasks SET status = ? WHERE id = ?');
            $upd->execute([$newStatus, $taskId]);
            set_flash('Task status updated successfully.');
        } else {
            set_flash('You do not have permission to modify this task.', 'error');
        }
    }
    header('Location: section.php?id=' . $sectionId);
    exit;
}

// Fetch approved members
$membersStmt = $db->prepare('
    SELECT u.id, u.name, u.student_id, u.email, u.department, sm.applied_at
    FROM section_members sm
    JOIN users u ON sm.user_id = u.id
    WHERE sm.section_id = ? AND sm.status = "approved"
    ORDER BY u.name ASC
');
$membersStmt->execute([$sectionId]);
$members = $membersStmt->fetchAll();
$approvedCount = count($members);

// Check if current user is an approved member or applied
$currentUserApp = null;
if ($user) {
    $myAppStmt = $db->prepare('SELECT status, applied_at, applicant_note FROM section_members WHERE section_id = ? AND user_id = ?');
    $myAppStmt->execute([$sectionId, $user['id']]);
    $currentUserApp = $myAppStmt->fetch();
}

$isApprovedMember = ($currentUserApp && $currentUserApp['status'] === 'approved');

// Fetch tasks for this section
$tasksStmt = $db->prepare('
    SELECT t.*, u.name AS assignee_name, u.student_id AS assignee_student_id
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to_user_id = u.id
    WHERE t.section_id = ?
    ORDER BY t.deadline ASC, t.id ASC
');
$tasksStmt->execute([$sectionId]);
$allTasks = $tasksStmt->fetchAll();

$tasksByStatus = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($allTasks as $t) {
    $st = in_array($t['status'], ['todo', 'in_progress', 'done'], true) ? $t['status'] : 'todo';
    $tasksByStatus[$st][] = $t;
}

$deadline = new DateTime($section['join_deadline']);
$isPastDeadline = (new DateTime()) > $deadline;
$isFull = $approvedCount >= $section['member_limit'];
$canApply = $section['joining_status'] === 'Open' && !$isPastDeadline && !$isFull && !$currentUserApp;

header_view($section['name'] . ' Section · ' . $section['event_title'], 'events');
?>

<div class="breadcrumb">
    <a href="index.php">← Events Calendar</a>
    <span>/</span>
    <a href="event.php?id=<?=$section['event_id']?>"><?=h($section['event_title'])?></a>
    <span>/</span>
    <span><?=h($section['name'])?> Section</span>
</div>

<div class="section-detail-header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;">
        <div>
            <span class="eyebrow">ORGANIZING SUB-TEAM</span>
            <h1><?=h($section['name'])?> Section</h1>
            <p style="font-size:15px; color:#475569; margin:4px 0 0;">
                Event: <a href="event.php?id=<?=$section['event_id']?>" style="font-weight:700; color:#1d4ed8;"><?=h($section['event_title'])?></a>
                &nbsp;·&nbsp; Hosted by <?=h($section['society'])?>
            </p>
        </div>
        
        <div style="display:flex; align-items:center; gap:10px;">
            <?php if ($section['joining_status'] === 'Open' && !$isPastDeadline && !$isFull): ?>
                <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:13px; padding:6px 14px;">
                    ● Open for Student Applications
                </span>
            <?php else: ?>
                <span class="badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; font-size:13px; padding:6px 14px;">
                    ● Joining Closed
                </span>
            <?php endif; ?>

            <?php if ($isManager): ?>
                <a href="manage-event.php?id=<?=$section['event_id']?>#tab-sections" class="btn-secondary" style="font-size:13px;">
                    ⚙ Manage Section
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="detail-layout">
    <!-- Main Left Column -->
    <div class="detail-main">
        <!-- Section Overview -->
        <div class="form-card" style="margin-bottom:32px;">
            <h2 style="font-size:18px; font-weight:800; margin:0 0 12px; color:#0f172a;">What This Team Does</h2>
            <p style="font-size:15px; color:#334155; line-height:1.7; margin:0 0 20px;"><?=nl2br(h($section['description']))?></p>

            <h3 style="font-size:15px; font-weight:800; margin:0 0 8px; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Main Responsibilities:</h3>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-size:14px; color:#334155; line-height:1.7;">
                <?=nl2br(h($section['responsibilities']))?>
            </div>

            <?php if (!empty($section['useful_skills'])): ?>
                <div style="margin-top:20px;">
                    <strong style="font-size:13px; color:#0f172a;">Useful Skills / Experience:</strong>
                    <p style="margin:4px 0 0; font-size:14px; color:#475569;"><?=h($section['useful_skills'])?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($section['resources'])): ?>
                <div style="margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
                    <strong style="font-size:13px; color:#0f172a;">Shared Documents & Resources:</strong>
                    <p style="margin:6px 0 0; font-size:14px; color:#1d4ed8; word-break:break-all;">
                        <?=nl2br(h($section['resources']))?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Task Management Board (To do, In progress, Done) -->
        <div style="margin-bottom:36px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h2 style="font-size:20px; font-weight:800; margin:0; color:#0f172a;">Section Tasks Board</h2>
                    <small style="color:#64748b;">Track deliverables and operational progress (To do → In progress → Done)</small>
                </div>
                <?php if ($isManager): ?>
                    <a href="manage-event.php?id=<?=$section['event_id']?>#tab-tasks" class="btn-secondary" style="font-size:12px; padding:6px 12px;">
                        + Assign New Task
                    </a>
                <?php endif; ?>
            </div>

            <div class="kanban-board">
                <!-- 1. To Do Column -->
                <div class="kanban-col">
                    <div class="kanban-col-header">
                        <h3><span>📌</span> To Do</h3>
                        <span class="kanban-col-count"><?=count($tasksByStatus['todo'])?></span>
                    </div>
                    <div class="task-list">
                        <?php if (empty($tasksByStatus['todo'])): ?>
                            <p style="font-size:12px; color:#94a3b8; text-align:center; padding:16px 0;">No pending tasks</p>
                        <?php else: ?>
                            <?php foreach ($tasksByStatus['todo'] as $task): ?>
                                <div class="task-card">
                                    <h4 class="task-title"><?=h($task['title'])?></h4>
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="task-desc"><?=h($task['description'])?></p>
                                    <?php endif; ?>
                                    <div class="task-footer">
                                        <span>👤 <?=h($task['assignee_name'] ?? 'Unassigned')?></span>
                                        <?php if (!empty($task['deadline'])): ?>
                                            <span>📅 <?=h((new DateTime($task['deadline']))->format('M d'))?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isManager || $isApprovedMember): ?>
                                        <form method="post" action="section.php?id=<?=$sectionId?>" style="margin-top:6px;">
                                            <input type="hidden" name="csrf" value="<?=token()?>">
                                            <input type="hidden" name="action" value="update_task_status">
                                            <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                            <input type="hidden" name="new_status" value="in_progress">
                                            <button type="submit" class="btn-secondary" style="font-size:11px; padding:4px 8px; width:100%; justify-content:center;">
                                                Start Task →
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. In Progress Column -->
                <div class="kanban-col" style="background:#f0f7ff; border-color:#bfdbfe;">
                    <div class="kanban-col-header" style="border-color:#bfdbfe;">
                        <h3 style="color:#1d4ed8;"><span>⚡</span> In Progress</h3>
                        <span class="kanban-col-count" style="border-color:#bfdbfe; color:#1d4ed8;"><?=count($tasksByStatus['in_progress'])?></span>
                    </div>
                    <div class="task-list">
                        <?php if (empty($tasksByStatus['in_progress'])): ?>
                            <p style="font-size:12px; color:#94a3b8; text-align:center; padding:16px 0;">No tasks currently active</p>
                        <?php else: ?>
                            <?php foreach ($tasksByStatus['in_progress'] as $task): ?>
                                <div class="task-card" style="border-color:#bfdbfe;">
                                    <h4 class="task-title" style="color:#1d4ed8;"><?=h($task['title'])?></h4>
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="task-desc"><?=h($task['description'])?></p>
                                    <?php endif; ?>
                                    <div class="task-footer">
                                        <span class="task-assignee">👤 <?=h($task['assignee_name'] ?? 'Unassigned')?></span>
                                        <?php if (!empty($task['deadline'])): ?>
                                            <span>📅 <?=h((new DateTime($task['deadline']))->format('M d'))?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isManager || $isApprovedMember): ?>
                                        <form method="post" action="section.php?id=<?=$sectionId?>" style="margin-top:6px;">
                                            <input type="hidden" name="csrf" value="<?=token()?>">
                                            <input type="hidden" name="action" value="update_task_status">
                                            <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                            <input type="hidden" name="new_status" value="done">
                                            <button type="submit" class="btn-primary" style="font-size:11px; padding:4px 8px; width:100%; justify-content:center; background:#059669; border-color:#059669;">
                                                ✓ Mark Complete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Completed Column -->
                <div class="kanban-col" style="background:#f0fdf4; border-color:#bbf7d0;">
                    <div class="kanban-col-header" style="border-color:#bbf7d0;">
                        <h3 style="color:#047857;"><span>✓</span> Done</h3>
                        <span class="kanban-col-count" style="border-color:#bbf7d0; color:#047857;"><?=count($tasksByStatus['done'])?></span>
                    </div>
                    <div class="task-list">
                        <?php if (empty($tasksByStatus['done'])): ?>
                            <p style="font-size:12px; color:#94a3b8; text-align:center; padding:16px 0;">No completed tasks yet</p>
                        <?php else: ?>
                            <?php foreach ($tasksByStatus['done'] as $task): ?>
                                <div class="task-card" style="border-color:#bbf7d0;">
                                    <h4 class="task-title" style="text-decoration:line-through; color:#64748b;"><?=h($task['title'])?></h4>
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="task-desc"><?=h($task['description'])?></p>
                                    <?php endif; ?>
                                    <div class="task-footer">
                                        <span style="color:#047857;">Completed</span>
                                        <span>👤 <?=h($task['assignee_name'] ?? 'Member')?></span>
                                    </div>
                                    <?php if ($isManager): ?>
                                        <form method="post" action="section.php?id=<?=$sectionId?>" style="margin-top:6px;">
                                            <input type="hidden" name="csrf" value="<?=token()?>">
                                            <input type="hidden" name="action" value="update_task_status">
                                            <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                            <input type="hidden" name="new_status" value="in_progress">
                                            <button type="submit" class="btn-link" style="font-size:10px; color:#64748b; display:block; width:100%; text-align:center;">
                                                Reopen Task
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved Team Members Roster -->
        <div>
            <h2 style="font-size:20px; font-weight:800; margin:0 0 16px; color:#0f172a;">
                Approved Team Members (<?=$approvedCount?> of <?=$section['member_limit']?>)
            </h2>
            <?php if (empty($members)): ?>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; text-align:center; color:#64748b;">
                    No student members have been approved yet. Applications are currently being reviewed.
                </div>
            <?php else: ?>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Department</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <tr>
                                    <td><strong><?=h($m['name'])?></strong></td>
                                    <td><span style="font-family:var(--font-mono); font-size:12px; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?=h($m['student_id'])?></span></td>
                                    <td><?=h($m['department'])?></td>
                                    <td><a href="mailto:<?=h($m['email'])?>"><?=h($m['email'])?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Section Meta & Apply Form -->
    <aside>
        <!-- Section Coordinator Card -->
        <div class="sidebar-card" style="margin-bottom:24px;">
            <h3>Section Coordinator</h3>
            <div class="sidebar-data-list">
                <div class="sidebar-data-item">
                    <label>Lead Coordinator</label>
                    <div class="value">
                        👤 <?=h($section['leader_name'])?>
                    </div>
                </div>
                <?php if (!empty($section['leader_email'])): ?>
                    <div class="sidebar-data-item">
                        <label>Coordinator Email</label>
                        <div class="value">
                            ✉ <a href="mailto:<?=h($section['leader_email'])?>"><?=h($section['leader_email'])?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="sidebar-data-item">
                    <label>Team Capacity</label>
                    <div class="value">
                        <strong><?=$approvedCount?></strong> / <?=$section['member_limit']?> Members
                        <div style="background:#e2e8f0; border-radius:999px; height:8px; width:100%; margin-top:8px; overflow:hidden;">
                            <?php $pct = min(100, round(($approvedCount / max(1, $section['member_limit'])) * 100)); ?>
                            <div style="background:#1d4ed8; height:100%; width:<?=$pct?>%;"></div>
                        </div>
                    </div>
                </div>
                <div class="sidebar-data-item">
                    <label>Application Deadline</label>
                    <div class="value">
                        📅 <?=h($deadline->format('F d, Y'))?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application / Status Box -->
        <div class="sidebar-card">
            <h3>Join this Organizing Team</h3>

            <?php if ($currentUserApp): ?>
                <div style="padding:16px; border-radius:8px; margin-bottom:12px; <?=$currentUserApp['status'] === 'approved' ? 'background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;' : ($currentUserApp['status'] === 'pending' ? 'background:#fffbeb; border:1px solid #fde68a; color:#92400e;' : 'background:#fef2f2; border:1px solid #fecaca; color:#991b1b;')?>">
                    <div style="font-weight:800; font-size:14px; margin-bottom:4px;">
                        <?=$currentUserApp['status'] === 'approved' ? '✓ You Are on the Team!' : ($currentUserApp['status'] === 'pending' ? '⏳ Application Under Review' : 'Application Not Accepted')?>
                    </div>
                    <p style="font-size:12px; margin:0; line-height:1.5;">
                        Applied on: <?=h((new DateTime($currentUserApp['applied_at']))->format('M d, Y · g:i A'))?><br>
                        <?php if (!empty($currentUserApp['applicant_note'])): ?>
                            <em>"<?=h($currentUserApp['applicant_note'])?>"</em>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="my-dashboard.php" class="btn-secondary" style="width:100%; justify-content:center;">
                    View on My Dashboard →
                </a>

            <?php elseif ($canApply): ?>
                <?php if ($user): ?>
                    <form method="post" action="section.php?id=<?=$sectionId?>">
                        <input type="hidden" name="csrf" value="<?=token()?>">
                        <input type="hidden" name="action" value="apply_section">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Applying as:</label>
                            <div style="background:#f1f5f9; padding:8px 12px; border-radius:4px; font-size:13px; font-weight:600;">
                                <?=h($user['name'])?> (<?=h($user['student_id'])?>)
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label for="applicant_note">Statement & Relevant Experience</label>
                            <textarea id="applicant_note" name="applicant_note" rows="3" placeholder="Briefly state why you'd like to join and any skills you have..." required></textarea>
                            <span class="help-text">Beginners are warmly welcomed!</span>
                        </div>

                        <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
                            Submit Section Application →
                        </button>
                    </form>
                <?php else: ?>
                    <p style="font-size:13px; color:#475569; line-height:1.6; margin-bottom:16px;">
                        Are you an undergraduate student from the Faculty of IT? Sign in with your university account to apply for this section.
                    </p>
                    <a href="login.php" class="btn-primary" style="width:100%; justify-content:center; margin-bottom:8px;">
                        Sign In to Apply
                    </a>
                    <a href="register.php" class="btn-secondary" style="width:100%; justify-content:center;">
                        Create Student Account
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px; font-size:13px; color:#64748b; line-height:1.5;">
                    <?php if ($isPastDeadline): ?>
                        Applications for this event closed on <?=h($deadline->format('M d, Y'))?>.
                    <?php elseif ($isFull): ?>
                        This section has reached its maximum capacity of <?=$section['member_limit']?> members.
                    <?php else: ?>
                        Applications are currently closed for this team.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php footer_view(); ?>
