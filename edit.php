<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    header('Location: manage-event.php?id=' . $id);
    exit;
} else {
    header('Location: create-event.php');
    exit;
}
