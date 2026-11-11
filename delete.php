<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = require_login('Please sign in to perform this deletion.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf();

$type = trim((string)($_POST['type'] ?? 'event'));
$id = (int)($_POST['id'] ?? 0);
$eventId = (int)($_POST['event_id'] ?? 0);

if ($id <= 0) {
    set_flash('Invalid item ID for deletion.', 'error');
    header('Location: index.php');
    exit;
}

// 1. DELETE EVENT
if ($type === 'event') {
    $stmt = $db->prepare('SELECT id, title, created_by FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $event = $stmt->fetch();

    if (!$event || !can_manage_event($event)) {
        http_response_code(403);
        set_flash('You do not have permission to delete this event.', 'error');
        header('Location: index.php');
        exit;
    }

    $del = $db->prepare('DELETE FROM events WHERE id = ?');
    $del->execute([$id]);

    set_flash('Event "' . $event['title'] . '" and all related sections and tasks were permanently deleted.');
    header('Location: index.php');
    exit;
}

// 2. DELETE SECTION
elseif ($type === 'section') {
    $stmt = $db->prepare('
        SELECT s.id, s.name, s.event_id, e.created_by
        FROM sections s
        JOIN events e ON s.event_id = e.id
        WHERE s.id = ?
    ');
    $stmt->execute([$id]);
    $sec = $stmt->fetch();

    if (!$sec || !can_manage_event($sec['created_by'])) {
        http_response_code(403);
        set_flash('You do not have permission to delete this section.', 'error');
        header('Location: index.php');
        exit;
    }

    $del = $db->prepare('DELETE FROM sections WHERE id = ?');
    $del->execute([$id]);

    set_flash('Section "' . $sec['name'] . '" was removed.');
    header('Location: manage-event.php?id=' . $sec['event_id'] . '#tab-sections');
    exit;
}

// 3. DELETE TASK
elseif ($type === 'task') {
    $stmt = $db->prepare('
        SELECT t.id, t.title, s.event_id, e.created_by
        FROM tasks t
        JOIN sections s ON t.section_id = s.id
        JOIN events e ON s.event_id = e.id
        WHERE t.id = ?
    ');
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task || !can_manage_event($task['created_by'])) {
        http_response_code(403);
        set_flash('You do not have permission to delete this task.', 'error');
        header('Location: index.php');
        exit;
    }

    $del = $db->prepare('DELETE FROM tasks WHERE id = ?');
    $del->execute([$id]);

    set_flash('Task "' . $task['title'] . '" was deleted.');
    header('Location: manage-event.php?id=' . $task['event_id'] . '#tab-tasks');
    exit;
}

// 4. DELETE APPLICATION RECORD
elseif ($type === 'application') {
    $stmt = $db->prepare('
        SELECT sm.id, s.event_id, e.created_by
        FROM section_members sm
        JOIN sections s ON sm.section_id = s.id
        JOIN events e ON s.event_id = e.id
        WHERE sm.id = ?
    ');
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app || !can_manage_event($app['created_by'])) {
        http_response_code(403);
        set_flash('You do not have permission to delete this application record.', 'error');
        header('Location: index.php');
        exit;
    }

    $del = $db->prepare('DELETE FROM section_members WHERE id = ?');
    $del->execute([$id]);

    set_flash('Application record removed.');
    header('Location: manage-event.php?id=' . $app['event_id'] . '#tab-applications');
    exit;
}

header('Location: index.php');
exit;
