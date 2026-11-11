<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
$_SESSION['flash'] = ['message' => 'You have been safely signed out. See you soon!', 'type' => 'success'];
header('Location: index.php');
exit;
