<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$period = ($_GET['period'] ?? '') === 'past' ? 'past' : 'upcoming';

$where = [];
$params = [];

if ($period === 'past') {
    $where[] = 'e.starts_at < NOW()';
} else {
    $where[] = 'e.starts_at >= NOW()';
}

if ($q !== '') {
    $where[] = '(e.title LIKE ? OR e.society LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)';
    $searchTerm = '%' . $q . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($category !== '' && in_array($category, event_categories(), true)) {
    $where[] = 'e.category = ?';
    $params[] = $category;
} else {
    $category = '';
}

if ($status !== '' && in_array($status, event_statuses(), true)) {
    $where[] = 'e.status = ?';
    $params[] = $status;
} else {
    $status = '';
}

$sql = '
    SELECT e.*, 
           COUNT(DISTINCT s.id) AS section_count,
           COUNT(DISTINCT sm.id) AS total_applications
    FROM events e
    LEFT JOIN sections s ON s.event_id = e.id
    LEFT JOIN section_members sm ON sm.section_id = s.id
    ' . (count($where) ? 'WHERE ' . implode(' AND ', $where) : '') . '
    GROUP BY e.id
    ORDER BY e.starts_at ' . ($period === 'past' ? 'DESC' : 'ASC');

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Global Stats for the hero
$totalUpcoming = (int)$db->query('SELECT COUNT(*) FROM events WHERE starts_at >= NOW()')->fetchColumn();
$totalSections = (int)$db->query('SELECT COUNT(*) FROM sections')->fetchColumn();
$totalMembers = (int)$db->query("SELECT COUNT(*) FROM section_members WHERE status = 'approved'")->fetchColumn();

header_view('Discover Events', 'events');
?>

<section class="hero">
    <div class="hero-top">
        <span class="eyebrow">FACULTY OF INFORMATION TECHNOLOGY · UOM</span>
        <div class="stats-pill-group">
            <span class="stats-pill"><strong><?=$totalUpcoming?></strong> Upcoming Events</span>
            <span class="stats-pill"><strong><?=$totalSections?></strong> Active Sections</span>
            <span class="stats-pill"><strong><?=$totalMembers?></strong> Student Volunteers</span>
        </div>
    </div>
    
    <h1 class="hero-headline">
        Where ideas take shape, <br>
        <span class="highlight">teams unite</span>, and Moratuwa leads.
    </h1>
    <p class="hero-subhead">
        Discover official events, tech hackathons, and workshops organized across the Faculty of Information Technology. 
        Join specialized organizing teams in <strong>Finance, Design, Logistics, Technical, Publicity</strong>, and build real-world leadership skills.
    </p>

    <!-- Filters Bar -->
    <div class="filters-bar" id="events-browser">
        <form class="filters-form" method="get" action="index.php#events-browser">
            <div class="filter-group search-group">
                <label for="q">Search Events</label>
                <input id="q" name="q" type="search" value="<?=h($q)?>" placeholder="Search by title, society (e.g. INTECS), venue..." maxlength="120">
            </div>

            <div class="filter-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach (event_categories() as $cat): ?>
                        <option value="<?=h($cat)?>" <?=$category === $cat ? 'selected' : ''?>><?=h($cat)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="status">Joining Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (event_statuses() as $st): ?>
                        <option value="<?=h($st)?>" <?=$status === $st ? 'selected' : ''?>><?=h($st)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="period">Timeline</label>
                <select id="period" name="period">
                    <option value="upcoming" <?=$period === 'upcoming' ? 'selected' : ''?>>Upcoming Events</option>
                    <option value="past" <?=$period === 'past' ? 'selected' : ''?>>Past Events</option>
                </select>
            </div>

            <button type="submit" class="btn-filter">
                <span>Filter</span> 🔍
            </button>
            <?php if ($q !== '' || $category !== '' || $status !== '' || $period === 'past'): ?>
                <a href="index.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<div class="results-meta">
    <span>Showing <strong><?=count($events)?></strong> <?=count($events) === 1 ? 'event' : 'events'?> on the faculty calendar</span>
    <span>Sri Lanka Standard Time (UTC+05:30)</span>
</div>

<div class="events-list">
    <?php if (empty($events)): ?>
        <div style="background:#ffffff; border:1px dashed #cbd5e1; border-radius:12px; padding:48px 24px; text-align:center;">
            <p style="font-size:36px; margin:0 0 10px;">📅</p>
            <h3 style="margin:0 0 8px; font-size:20px; color:#0f172a;">No events found matching your criteria.</h3>
            <p style="color:#64748b; margin:0 0 20px;">Try adjusting your keyword or clearing filters to view all scheduled university events.</p>
            <a href="index.php" class="btn-secondary">Clear All Filters</a>
        </div>
    <?php else: ?>
        <?php foreach ($events as $event): 
            $dt = new DateTime($event['starts_at']);
            $deadline = new DateTime($event['join_deadline']);
            $isOwner = can_manage_event($event);
        ?>
            <article class="event-card">
                <!-- Date Box -->
                <div class="date-box">
                    <span class="month"><?=h($dt->format('M'))?></span>
                    <span class="day"><?=h($dt->format('d'))?></span>
                    <span class="dayname"><?=h($dt->format('D'))?></span>
                </div>

                <!-- Event Details -->
                <div class="event-info">
                    <div class="event-tags-row">
                        <span class="tag-category"><?=h($event['category'])?></span>
                        <?=render_status_badge($event['status'])?>
                        <?php if ($event['section_count'] > 0): ?>
                            <span class="sections-pill">✦ <?=$event['section_count']?> Organizing Sections</span>
                        <?php endif; ?>
                    </div>

                    <h2 class="event-title">
                        <a href="event.php?id=<?=$event['id']?>"><?=h($event['title'])?></a>
                    </h2>

                    <div class="event-society">
                        <span class="host-label">Organized by:</span>
                        <strong><?=h($event['society'])?></strong>
                    </div>

                    <div class="event-meta-row">
                        <span class="event-meta-item">
                            <span>🕒</span> <?=h($dt->format('g:i A'))?>
                        </span>
                        <span class="event-meta-item">
                            <span>📍</span> <?=h($event['venue'])?>
                        </span>
                        <span class="event-meta-item" title="Deadline to apply for organizing sections">
                            <span>⏳</span> Apply by: <strong><?=h($deadline->format('M d, Y'))?></strong>
                        </span>
                    </div>
                </div>

                <!-- Event Actions -->
                <div class="event-actions">
                    <a href="event.php?id=<?=$event['id']?>" class="btn-primary">
                        View Details & Sections →
                    </a>
                    <?php if ($isOwner): ?>
                        <a href="manage-event.php?id=<?=$event['id']?>" class="btn-secondary" style="font-size:12px; padding:6px 12px;">
                            ⚙ Manage Event
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php footer_view(); ?>
