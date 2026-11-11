<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/templates.php';

// Database connection
$config = require __DIR__ . '/../config.php';
try {
    $db = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $db->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    http_response_code(503);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Database Setup Required · Campus Gather</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body style="background:#f8fafc; font-family:system-ui,-apple-system,sans-serif; padding:40px 20px;">
        <div style="max-width:680px; margin:40px auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:32px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="display:inline-block; padding:4px 10px; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:12px; border-radius:999px; letter-spacing:1px; margin-bottom:16px;">LOCAL SERVER SETUP</div>
            <h1 style="color:#0f172a; margin:0 0 16px 0; font-size:26px;">Database Not Found or Offline</h1>
            <p style="color:#475569; line-height:1.6; margin-bottom:20px;">PHP cannot connect to MySQL with the current settings in <code>config.php</code>. Error: <em><?=htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')?></em></p>
            <div style="background:#f1f5f9; padding:20px; border-radius:8px; font-size:14px; line-height:1.7; color:#334155;">
                <strong>Quick Fix for XAMPP:</strong>
                <ol style="margin:10px 0 0 20px; padding:0;">
                    <li>Open XAMPP Control Panel and start <strong>MySQL</strong> and <strong>Apache</strong>.</li>
                    <li>Open <a href="http://localhost/phpmyadmin/" target="_blank" style="color:#1d4ed8; text-decoration:underline;">phpMyAdmin</a>.</li>
                    <li>Click <strong>Import</strong> tab at the top and select <code>database.sql</code> from this project folder.</li>
                    <li>Check that <code>config.php</code> matches your local credentials (default: username <code>root</code>, blank password).</li>
                    <li>Refresh this page.</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Escaping helper
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// User & Authentication Helpers
function current_user(): ?array {
    global $db;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }
    $stmt = $db->prepare('SELECT id, name, student_id, email, department, role FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name']);
        return null;
    }
    $cachedUser = $user;
    return $cachedUser;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_login(string $redirectMessage = 'Please sign in to continue.'): array {
    $user = current_user();
    if (!$user) {
        $_SESSION['flash'] = ['message' => $redirectMessage, 'type' => 'error'];
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    return $user;
}

function can_manage_event($event): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'admin') {
        return true;
    }
    $creatorId = is_array($event) ? (int)($event['created_by'] ?? 0) : (int)$event;
    return $creatorId === (int)$user['id'];
}

// CSRF Protection
function token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(token(), $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and submit the form again.');
    }
}

// Flash Notifications
function set_flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : ['message' => $flash, 'type' => 'success'];
    }
    return null;
}

// Categories & Statuses
function event_categories(): array {
    return ['Technology', 'Workshop', 'Competition', 'Cultural', 'Sports', 'Community', 'Academic', 'Other'];
}

function event_statuses(): array {
    return ['Planning', 'Open for joining', 'In progress', 'Completed', 'Cancelled'];
}

// Visual Badges
function render_status_badge(string $status): string {
    $map = [
        'Open for joining' => ['bg' => '#ecfdf5', 'color' => '#065f46', 'border' => '#a7f3d0'],
        'Planning'         => ['bg' => '#f8fafc', 'color' => '#334155', 'border' => '#cbd5e1'],
        'In progress'      => ['bg' => '#eff6ff', 'color' => '#1e40af', 'border' => '#bfdbfe'],
        'Completed'        => ['bg' => '#faf5ff', 'color' => '#6b21a8', 'border' => '#e9d5ff'],
        'Cancelled'        => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca'],
    ];
    $style = $map[$status] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1'];
    return sprintf(
        '<span class="badge" style="background:%s; color:%s; border:1px solid %s;">● %s</span>',
        $style['bg'], $style['color'], $style['border'], h($status)
    );
}

function render_task_badge(string $status): string {
    $map = [
        'todo'        => ['label' => 'To Do', 'bg' => '#f8fafc', 'color' => '#475569', 'border' => '#cbd5e1'],
        'in_progress' => ['label' => 'In Progress', 'bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
        'done'        => ['label' => 'Completed', 'bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0'],
    ];
    $b = $map[$status] ?? ['label' => ucfirst($status), 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
    return sprintf(
        '<span class="task-badge" style="background:%s; color:%s; border:1px solid %s;">%s</span>',
        $b['bg'], $b['color'], $b['border'], h($b['label'])
    );
}

// Header Layout (White & Blue High-Contrast Theme)
function header_view(string $title, string $active = ''): void {
    $user = current_user();
    $flash = get_flash();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=h($title)?> · Campus Gather · FIT UoM</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
    <header class="navbar">
        <div class="nav-container">
            <a class="brand" href="index.php">
                <span class="brand-crest">UOM</span>
                <span class="brand-text">
                    <strong>CampusGather</strong>
                    <small>Faculty of Information Technology</small>
                </span>
            </a>
            
            <nav class="nav-links" aria-label="Main Navigation">
                <a href="index.php" class="<?=$active === 'events' ? 'active' : ''?>">Events Calendar</a>
                <a href="create-event.php" class="btn-create-nav <?=$active === 'create' ? 'active' : ''?>">+ Create Event</a>
                
                <?php if ($user): ?>
                    <a href="my-dashboard.php" class="<?=$active === 'dashboard' ? 'active' : ''?>">My Dashboard</a>
                    <div class="user-menu">
                        <span class="user-pill" title="<?=h($user['department'])?>">
                            <span class="status-dot"></span>
                            <?=h($user['name'])?> (<?=h($user['student_id'])?>)
                        </span>
                        <form action="logout.php" method="post" style="display:inline; margin:0;">
                            <input type="hidden" name="csrf" value="<?=token()?>">
                            <button type="submit" class="btn-link">Sign Out</button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-nav-login <?=$active === 'login' ? 'active' : ''?>">Sign In</a>
                    <a href="register.php" class="btn-nav-register <?=$active === 'register' ? 'active' : ''?>">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="page-container">
        <?php if ($flash): ?>
            <div class="alert alert-<?=$flash['type'] === 'error' ? 'error' : 'success'?>" role="alert">
                <span class="alert-icon"><?=$flash['type'] === 'error' ? '⚠' : '✓'?></span>
                <span><?=h($flash['message'])?></span>
            </div>
        <?php endif; ?>
    <?php
}

// Footer Layout
function footer_view(): void {
    ?>
    </main>
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-col brand-col">
                <div class="brand">
                    <span class="brand-crest">UOM</span>
                    <span class="brand-text">
                        <strong>CampusGather</strong>
                        <small>University of Moratuwa</small>
                    </span>
                </div>
                <p>A structured, collaborative event and section management platform designed for the Faculty of Information Technology, University of Moratuwa.</p>
            </div>
            
            <div class="footer-col">
                <h4>Platform Navigation</h4>
                <ul>
                    <li><a href="index.php">Upcoming Events Calendar</a></li>
                    <li><a href="create-event.php">Launch New Event</a></li>
                    <li><a href="my-dashboard.php">Student Dashboard & Tasks</a></li>
                    <li><a href="login.php">Organizer & Student Login</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Organizing Sections</h4>
                <ul class="footer-tags">
                    <li>Finance</li>
                    <li>Design</li>
                    <li>Publicity & Media</li>
                    <li>Logistics</li>
                    <li>Technical</li>
                    <li>Programme</li>
                    <li>Sponsorship</li>
                    <li>Registration</li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Academic Credit</h4>
                <p>Developed for <strong>Assignment 02</strong> (Web Application Development). Built using pure HTML5, CSS3, JavaScript, PHP 8+, and MySQL.</p>
                <small class="text-muted">Faculty of Information Technology · Katubedda, Moratuwa</small>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?=date('Y')?> Faculty of Information Technology, University of Moratuwa. All rights reserved.</p>
            <p><a href="#top" class="back-to-top">Back to top ↑</a></p>
        </div>
    </footer>
</body>
</html>
    <?php
}
