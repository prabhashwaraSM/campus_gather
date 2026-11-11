<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = require_login('Please sign in to view your dashboard and personal tasks.');

// Handle Task Status Toggle from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_my_task') {
    csrf();
    $taskId = (int)($_POST['task_id'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));

    if (in_array($newStatus, ['todo', 'in_progress', 'done'], true)) {
        $upd = $db->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to_user_id = ?');
        $upd->execute([$newStatus, $taskId, $user['id']]);
        set_flash('Your task status has been updated.');
    }
    header('Location: my-dashboard.php#tab-tasks');
    exit;
}

// 1. Fetch My Section Applications & Teams
$appsStmt = $db->prepare('
    SELECT sm.*, 
           s.name AS section_name, s.leader_name, s.leader_email,
           e.id AS event_id, e.title AS event_title, e.starts_at, e.status AS event_status
    FROM section_members sm
    JOIN sections s ON sm.section_id = s.id
    JOIN events e ON s.event_id = e.id
    WHERE sm.user_id = ?
    ORDER BY sm.applied_at DESC
');
$appsStmt->execute([$user['id']]);
$myApplications = $appsStmt->fetchAll();

// 2. Fetch My Assigned Tasks
$tasksStmt = $db->prepare('
    SELECT t.*, s.name AS section_name, e.id AS event_id, e.title AS event_title
    FROM tasks t
    JOIN sections s ON t.section_id = s.id
    JOIN events e ON s.event_id = e.id
    WHERE t.assigned_to_user_id = ?
    ORDER BY t.status = "done" ASC, t.deadline ASC, t.id DESC
');
$tasksStmt->execute([$user['id']]);
$myTasks = $tasksStmt->fetchAll();

// 3. Fetch Events Organized by Me
$myEventsStmt = $db->prepare('
    SELECT e.*, 
           COUNT(DISTINCT s.id) AS section_count,
           COUNT(DISTINCT CASE WHEN sm.status = "pending" THEN sm.id END) AS pending_apps
    FROM events e
    LEFT JOIN sections s ON s.event_id = e.id
    LEFT JOIN section_members sm ON sm.section_id = s.id
    WHERE e.created_by = ?
    GROUP BY e.id
    ORDER BY e.starts_at DESC
');
$myEventsStmt->execute([$user['id']]);
$myEvents = $myEventsStmt->fetchAll();

header_view('My Student Dashboard', 'dashboard');
?>

<div class="breadcrumb">
    <a href="index.php">← Events Calendar</a>
    <span>/</span>
    <span>Student Portal</span>
</div>

<div class="hero-top" style="margin-bottom:28px;">
    <div>
        <span class="eyebrow">FACULTY OF IT · STUDENT PORTAL</span>
        <h1 style="font-size:30px; font-weight:800; margin:6px 0 4px; color:#0f172a;">
            Welcome back, <?=h($user['name'])?>!
        </h1>
        <p style="color:#64748b; font-size:14px; margin:0;">
            Student ID: <strong><?=h($user['student_id'])?></strong> &nbsp;·&nbsp; 
            Department: <strong><?=h($user['department'])?></strong> &nbsp;·&nbsp;
            Role: <strong><?=ucfirst(h($user['role']))?></strong>
        </p>
    </div>
    <div>
        <a href="create-event.php" class="btn-primary">+ Launch New Event</a>
    </div>
</div>

<div class="tabs-container">
    <nav class="tabs-nav" aria-label="Dashboard navigation">
        <button type="button" class="tab-btn active" data-tab="applications">
            <span>👥 My Section Teams (<?=count($myApplications)?>)</span>
        </button>
        <button type="button" class="tab-btn" data-tab="tasks">
            <span>📋 My Assigned Tasks (<?=count($myTasks)?>)</span>
        </button>
        <button type="button" class="tab-btn" data-tab="events">
            <span>🎪 Events I Organize (<?=count($myEvents)?>)</span>
        </button>
    </nav>

    <!-- TAB 1: MY APPLICATIONS & JOINED SECTIONS -->
    <div class="tab-pane active" id="tab-applications">
        <h2 style="font-size:18px; font-weight:800; margin:0 0 16px; color:#0f172a;">
            My Section Applications & Active Teams
        </h2>

        <?php if (empty($myApplications)): ?>
            <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:36px; text-align:center;">
                <p style="font-size:32px; margin:0 0 8px;">🤝</p>
                <h3 style="margin:0 0 8px; color:#0f172a;">You haven't joined any organizing teams yet.</h3>
                <p style="color:#64748b; margin:0 0 16px;">Browse upcoming faculty events and apply for sections in Design, Technical, Publicity, Logistics, and more!</p>
                <a href="index.php" class="btn-primary">Browse Upcoming Events</a>
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Section</th>
                            <th>Team Leader</th>
                            <th>Application Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myApplications as $app): ?>
                            <tr>
                                <td>
                                    <strong><a href="event.php?id=<?=$app['event_id']?>"><?=h($app['event_title'])?></a></strong>
                                </td>
                                <td>
                                    <span class="sections-pill">✦ <?=h($app['section_name'])?></span>
                                </td>
                                <td>
                                    <?=h($app['leader_name'])?>
                                    <?php if (!empty($app['leader_email'])): ?>
                                        <br><small><a href="mailto:<?=h($app['leader_email'])?>"><?=h($app['leader_email'])?></a></small>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:#64748b;">
                                    <?=h((new DateTime($app['applied_at']))->format('M d, Y'))?>
                                </td>
                                <td>
                                    <?php if ($app['status'] === 'approved'): ?>
                                        <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;">✓ Approved Member</span>
                                    <?php elseif ($app['status'] === 'pending'): ?>
                                        <span class="badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">⏳ Under Review</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca;">✕ Not Accepted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="section.php?id=<?=$app['section_id']?>" class="btn-secondary" style="font-size:11px; padding:4px 10px;">
                                        View Section Board →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: MY ASSIGNED TASKS (READ & UPDATE) -->
    <div class="tab-pane" id="tab-tasks">
        <h2 style="font-size:18px; font-weight:800; margin:0 0 16px; color:#0f172a;">
            My Operational Tasks & Deliverables
        </h2>

        <?php if (empty($myTasks)): ?>
            <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:36px; text-align:center; color:#64748b;">
                You currently have no tasks assigned to you. Once your section leader or organizer assigns tasks, they will appear here.
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task Title & Instructions</th>
                            <th>Section & Event</th>
                            <th>Deadline</th>
                            <th>Current Status</th>
                            <th>Quick Status Toggle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myTasks as $t): ?>
                            <tr>
                                <td>
                                    <strong><?=h($t['title'])?></strong>
                                    <?php if (!empty($t['description'])): ?>
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;"><?=h($t['description'])?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="sections-pill">✦ <?=h($t['section_name'])?></span><br>
                                    <small><a href="event.php?id=<?=$t['event_id']?>"><?=h($t['event_title'])?></a></small>
                                </td>
                                <td style="font-size:12px; white-space:nowrap;">
                                    <?=$t['deadline'] ? h((new DateTime($t['deadline']))->format('M d, Y')) : 'No deadline'?>
                                </td>
                                <td>
                                    <?=render_task_badge($t['status'])?>
                                </td>
                                <td>
                                    <form method="post" action="my-dashboard.php" style="display:flex; gap:6px; margin:0;">
                                        <input type="hidden" name="csrf" value="<?=token()?>">
                                        <input type="hidden" name="action" value="toggle_my_task">
                                        <input type="hidden" name="task_id" value="<?=$t['id']?>">

                                        <?php if ($t['status'] === 'todo'): ?>
                                            <input type="hidden" name="new_status" value="in_progress">
                                            <button type="submit" class="btn-primary" style="font-size:11px; padding:4px 8px;">
                                                Start Task →
                                            </button>
                                        <?php elseif ($t['status'] === 'in_progress'): ?>
                                            <input type="hidden" name="new_status" value="done">
                                            <button type="submit" class="btn-primary" style="font-size:11px; padding:4px 8px; background:#059669; border-color:#059669;">
                                                ✓ Mark Done
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="in_progress">
                                            <button type="submit" class="btn-secondary" style="font-size:11px; padding:4px 8px;">
                                                Reopen
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: EVENTS I ORGANIZE -->
    <div class="tab-pane" id="tab-events">
        <h2 style="font-size:18px; font-weight:800; margin:0 0 16px; color:#0f172a;">
            Events Created & Managed by Me
        </h2>

        <?php if (empty($myEvents)): ?>
            <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:36px; text-align:center;">
                <p style="font-size:32px; margin:0 0 8px;">🎪</p>
                <h3 style="margin:0 0 8px; color:#0f172a;">You haven't created any events yet.</h3>
                <p style="color:#64748b; margin:0 0 16px;">Take the lead! Organize a hackathon, seminar, or workshop for the faculty.</p>
                <a href="create-event.php" class="btn-primary">+ Create Your First Event</a>
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Sections</th>
                            <th>Pending Applications</th>
                            <th>Status</th>
                            <th>Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myEvents as $e): ?>
                            <tr>
                                <td>
                                    <strong><a href="event.php?id=<?=$e['id']?>"><?=h($e['title'])?></a></strong><br>
                                    <small style="color:#64748b;"><?=h($e['society'])?></small>
                                </td>
                                <td style="font-size:12px; white-space:nowrap;">
                                    <?=h((new DateTime($e['starts_at']))->format('M d, Y · g:i A'))?>
                                </td>
                                <td style="font-size:12px;"><?=h($e['venue'])?></td>
                                <td>
                                    <span class="sections-pill">✦ <?=$e['section_count']?> Teams</span>
                                </td>
                                <td>
                                    <?php if ($e['pending_apps'] > 0): ?>
                                        <span style="background:#dc2626; color:#ffffff; font-size:11px; padding:2px 8px; border-radius:999px; font-weight:800;">
                                            <?=$e['pending_apps']?> Pending
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#64748b; font-size:12px;">0 Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?=render_status_badge($e['status'])?>
                                </td>
                                <td>
                                    <a href="manage-event.php?id=<?=$e['id']?>" class="btn-primary" style="font-size:11px; padding:5px 12px;">
                                        ⚙ Manage Event
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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
