<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid event requested.', 'error');
    header('Location: index.php');
    exit;
}

// Fetch event
$stmt = $db->prepare('
    SELECT e.*, u.name AS creator_name, u.email AS creator_email, u.student_id AS creator_student_id
    FROM events e
    JOIN users u ON e.created_by = u.id
    WHERE e.id = ?
');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    header_view('Event Not Found');
    echo '<div style="text-align:center; padding:60px 20px;"><h2>Event not found.</h2><p>The event you are looking for does not exist or has been removed.</p><a href="index.php" class="btn-primary">Return to Events Calendar</a></div>';
    footer_view();
    exit;
}

// Fetch sections for this event with member count and task count
$secStmt = $db->prepare('
    SELECT s.*,
           COUNT(DISTINCT CASE WHEN sm.status = "approved" THEN sm.id END) AS approved_count,
           COUNT(DISTINCT sm.id) AS total_applicant_count,
           COUNT(DISTINCT t.id) AS task_count,
           COUNT(DISTINCT CASE WHEN t.status = "done" THEN t.id END) AS done_task_count
    FROM sections s
    LEFT JOIN section_members sm ON sm.section_id = s.id
    LEFT JOIN tasks t ON t.section_id = s.id
    WHERE s.event_id = ?
    GROUP BY s.id
    ORDER BY s.id ASC
');
$secStmt->execute([$id]);
$sections = $secStmt->fetchAll();

$user = current_user();
$isManager = can_manage_event($event);

// Check user application status across sections of this event
$userApplications = [];
if ($user) {
    $userAppsStmt = $db->prepare('
        SELECT sm.section_id, sm.status 
        FROM section_members sm
        JOIN sections s ON sm.section_id = s.id
        WHERE s.event_id = ? AND sm.user_id = ?
    ');
    $userAppsStmt->execute([$id, $user['id']]);
    while ($row = $userAppsStmt->fetch()) {
        $userApplications[$row['section_id']] = $row['status'];
    }
}

$eventDate = new DateTime($event['starts_at']);
$deadline = new DateTime($event['join_deadline']);
$isPastDeadline = (new DateTime()) > $deadline;

header_view($event['title'], 'events');
?>

<div class="breadcrumb">
    <a href="index.php">← Events Calendar</a>
    <span>/</span>
    <span><?=h($event['category'])?></span>
    <span>/</span>
    <span><?=h($event['title'])?></span>
</div>

<div class="detail-layout">
    <!-- Main Content Area -->
    <div class="detail-main">
        <div class="detail-header">
            <div class="event-tags-row">
                <span class="tag-category"><?=h($event['category'])?></span>
                <?=render_status_badge($event['status'])?>
                <span class="tag-category" style="background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                    🎓 <?=h($event['society'])?>
                </span>
            </div>

            <h1><?=h($event['title'])?></h1>
            
            <p style="font-size:15px; color:#475569; margin:4px 0 0;">
                Main Coordinator: <strong><?=h($event['organiser_name'])?></strong> &nbsp;·&nbsp; 
                Contact: <a href="mailto:<?=h($event['contact_email'])?>"><?=h($event['contact_email'])?></a>
            </p>
        </div>

        <section style="margin-bottom:40px;">
            <h2 style="font-size:20px; font-weight:800; margin:0 0 12px; color:#0f172a;">About this Event</h2>
            <div class="event-description"><?=nl2br(h($event['description']))?></div>
        </section>

        <!-- Organizing Sections Grid -->
        <section>
            <div class="sections-heading-bar">
                <h2>
                    <span>👥 Organizing Sections</span>
                    <span style="font-size:14px; font-weight:600; color:#1d4ed8; background:#eff6ff; padding:2px 10px; border-radius:999px; border:1px solid #bfdbfe;">
                        <?=count($sections)?> Teams Available
                    </span>
                </h2>
                <?php if ($isManager): ?>
                    <a href="manage-event.php?id=<?=$event['id']?>#tab-sections" class="btn-secondary" style="font-size:12px; padding:6px 12px;">
                        + Manage / Add Sections
                    </a>
                <?php endif; ?>
            </div>

            <p style="color:#64748b; font-size:14px; margin:-8px 0 24px;">
                Select a section to view detailed responsibilities, team leader details, ongoing tasks, and apply to become an organizing team member.
            </p>

            <?php if (empty($sections)): ?>
                <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:8px; padding:32px; text-align:center;">
                    <p style="color:#64748b; margin:0 0 12px;">No organizing sections have been created for this event yet.</p>
                    <?php if ($isManager): ?>
                        <a href="manage-event.php?id=<?=$event['id']?>#tab-sections" class="btn-primary">Add Sections Now</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="sections-grid">
                    <?php foreach ($sections as $sec): 
                        $isFull = $sec['approved_count'] >= $sec['member_limit'];
                        $isOpen = $sec['joining_status'] === 'Open' && !$isFull && !$isPastDeadline && $event['status'] !== 'Completed' && $event['status'] !== 'Cancelled';
                        $myAppStatus = $userApplications[$sec['id']] ?? null;
                    ?>
                        <div class="section-card">
                            <div>
                                <div class="section-card-top">
                                    <h3 class="section-name">
                                        <span>✦</span> <?=h($sec['name'])?>
                                    </h3>
                                    <?php if ($isOpen): ?>
                                        <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;">Open</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca;">Closed</span>
                                    <?php endif; ?>
                                </div>

                                <p class="section-desc"><?=h($sec['description'])?></p>

                                <div class="section-meta-list">
                                    <div class="section-meta-item">
                                        <span>👤 <strong>Leader:</strong> <?=h($sec['leader_name'])?></span>
                                    </div>
                                    <div class="section-meta-item">
                                        <span>👥 <strong>Capacity:</strong> <?=$sec['approved_count']?> / <?=$sec['member_limit']?> members filled</span>
                                    </div>
                                    <?php if ($sec['task_count'] > 0): ?>
                                        <div class="section-meta-item">
                                            <span>📋 <strong>Tasks:</strong> <?=$sec['done_task_count']?> of <?=$sec['task_count']?> completed</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($sec['useful_skills'])): ?>
                                        <div class="section-meta-item" style="margin-top:4px;">
                                            <span>💡 <strong>Skills:</strong> <?=h($sec['useful_skills'])?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="margin-top:16px; border-top:1px solid #f1f5f9; padding-top:14px; display:flex; flex-direction:column; gap:8px;">
                                <?php if ($myAppStatus === 'approved'): ?>
                                    <span style="font-size:12px; font-weight:700; color:#047857; text-align:center; background:#ecfdf5; padding:6px; border-radius:4px; border:1px solid #a7f3d0;">
                                        ✓ You are an approved member
                                    </span>
                                <?php elseif ($myAppStatus === 'pending'): ?>
                                    <span style="font-size:12px; font-weight:700; color:#b45309; text-align:center; background:#fffbeb; padding:6px; border-radius:4px; border:1px solid #fde68a;">
                                        ⏳ Application Pending Review
                                    </span>
                                <?php elseif ($myAppStatus === 'rejected'): ?>
                                    <span style="font-size:12px; font-weight:600; color:#991b1b; text-align:center; background:#fef2f2; padding:6px; border-radius:4px;">
                                        Application not accepted
                                    </span>
                                <?php endif; ?>

                                <a href="section.php?id=<?=$sec['id']?>" class="btn-primary" style="justify-content:center;">
                                    View Section & Tasks →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Right Sidebar Information Card -->
    <aside>
        <div class="sidebar-card">
            <h3>Event Information</h3>
            
            <div class="sidebar-data-list">
                <div class="sidebar-data-item">
                    <label>Date & Time</label>
                    <div class="value">
                        <?=h($eventDate->format('l, F d, Y'))?>
                        <small>Starts at <?=h($eventDate->format('g:i A'))?> (Sri Lanka Time)</small>
                    </div>
                </div>

                <div class="sidebar-data-item">
                    <label>Event Venue</label>
                    <div class="value">
                        📍 <?=h($event['venue'])?>
                    </div>
                </div>

                <div class="sidebar-data-item">
                    <label>Section Joining Deadline</label>
                    <div class="value">
                        📅 <?=h($deadline->format('F d, Y'))?>
                        <?php if ($isPastDeadline): ?>
                            <small style="color:#dc2626; font-weight:600;">(Applications Closed)</small>
                        <?php else: ?>
                            <small style="color:#059669; font-weight:600;">(Open for student applications)</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-data-item">
                    <label>Organizing Society</label>
                    <div class="value">
                        <?=h($event['society'])?>
                    </div>
                </div>

                <div class="sidebar-data-item">
                    <label>Primary Contact</label>
                    <div class="value">
                        <?=h($event['organiser_name'])?>
                        <small>✉ <a href="mailto:<?=h($event['contact_email'])?>"><?=h($event['contact_email'])?></a></small>
                        <?php if (!empty($event['contact_phone'])): ?>
                            <small>📞 <?=h($event['contact_phone'])?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-data-item">
                    <label>Entry Fee</label>
                    <div class="value" style="color:#047857; font-weight:700;">
                        Free for all students
                    </div>
                </div>
            </div>

            <div class="sidebar-actions">
                <?php if ($isManager): ?>
                    <a href="manage-event.php?id=<?=$event['id']?>" class="btn-primary" style="justify-content:center;">
                        ⚙ Manage Event & Sections
                    </a>
                    <form method="post" action="delete.php" data-confirm="Are you sure you want to permanently delete this event and all its sections and tasks? This cannot be undone." style="margin:0;">
                        <input type="hidden" name="csrf" value="<?=token()?>">
                        <input type="hidden" name="type" value="event">
                        <input type="hidden" name="id" value="<?=$event['id']?>">
                        <button type="submit" class="btn-danger" style="width:100%; justify-content:center;">
                            Delete Event
                        </button>
                    </form>
                <?php else: ?>
                    <a href="#events" class="btn-secondary" style="justify-content:center;" onclick="window.scrollTo({top: 600, behavior: 'smooth'}); return false;">
                        Browse Organizing Sections ↓
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<?php footer_view(); ?>
